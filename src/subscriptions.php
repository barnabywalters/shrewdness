<?php

namespace Taproot\Shrewdness\Subscriptions;

use Symfony\Component\HttpFoundation as Http;
use Symfony\Component\EventDispatcher;
use Guzzle;
use Mf2;
use Exception;
use Taproot;
use DateTime;
use PDO;

class PdoSubscriptionStorage {
	protected $db;
	protected $prefix;
	
	public function __construct(PDO $pdo, $tablePrefix = 'shrewdness_') {
		$this->db = $pdo;
		$this->prefix = $tablePrefix;
	}
	
	public function getSubscriptions() {
		return $this->db->query('SELECT * FROM ' . $this->prefix . 'subscriptions;')->fetchAll();
	}
	
	public function createSubscription($topic, $hub) {
		$subscription = [
			'topic' => $topic,
			'hub' => $hub->getUrl()
		];
		
		$existingSubscription = $this->db->prepare('SELECT * FROM ' . $this->prefix . 'subscriptions WHERE topic = :topic AND hub = :hub;');
		$existingSubscription->execute($subscription);
		if ($existingSubscription->rowCount() !== 0) {
			$subscription = $existingSubscription->fetch();
			if ($subscription['mode'] !== 'subscribe') {
				$this->db->prepare("UPDATE {$this->prefix}subscriptions SET mode='subscribe' WHERE id = :id;")->execute($subscription);
			}
		} else {
			$this->db->prepare('INSERT INTO ' . $this->prefix . 'subscriptions (topic, hub) VALUES (:topic, :hub);')->execute($subscription);
			$existingSubscription->execute($subscription);
			$subscription = $existingSubscription->fetch();
		}
		
		return $subscription;
	}
	
	public function getSubscription($id) {
		return $this->db->query("SELECT * FROM {$this->prefix}subscriptions WHERE id = {$this->db->quote($id)};")->fetch();
	}
	
	public function getPingsForSubscription($id, $limit=20, $offset=0) {
		return $this->db->query("SELECT * FROM {$this->prefix}pings WHERE subscription = {$this->db->quote($id)} ORDER BY datetime DESC LIMIT {$limit} OFFSET {$offset};")->fetchAll();
	}
	
	public function subscriptionIntentVerified($id) {
		$this->db->exec("UPDATE {$this->prefix}subscriptions SET intent_verified = 1 WHERE id = {$this->db->quote($id)};");
	}
	
	public function getLatestPingForSubscription($id) {
		return $this->db->query("SELECT * FROM {$this->prefix}pings WHERE subscription = {$this->db->quote($id)} ORDER BY datetime DESC LIMIT 1;")->fetch();
	}
	
	public function createPing(array $ping) {
		$insertPing = $this->db->prepare('INSERT INTO ' . $this->prefix . 'pings (subscription, content_type, content) VALUES (:subscription, :content_type, :content);');
		$insertPing->execute($ping);
	}
	
	public function getPing($subscriptionId, $timestamp) {
		$fetchPing = $this->db->prepare("SELECT * FROM {$this->prefix}pings WHERE subscription = :subscription AND datetime = :timestamp;");
		$fetchPing->execute(['subscription' => $id, 'timestamp' => $timestamp]);
		return $fetchPing->fetch();
	}
}


// Returns [array $subscription|null, Exception $error|null]
function subscribe($storage, $defaultHub, $client, $url, $callbackUrlCreator) {
	// Discover Hub.
	try {
		$resp = $client->get($url)->send();
	} catch (Guzzle\Common\Exception\GuzzleException $e) {
		// Resource does not exist, return exception
		return $app->abort(400, "The given topic, {$url}, could not be fetched.");
	}
	
	$links = Taproot\pushLinksForResponse($resp);
	$topic = $links['self'] ?: $url;
	$hubs = $links['hub'];
	
	$hub = empty($hubs) ? $defaultHub : new Taproot\PushHub($hubs[0]);
	
	$subscription = $storage->createSubscription($topic, $hub);
	// Regardless of the state of the database beforehand, $subscription now exists, has an ID and a mode of “subscribe”.
	
	$result = $hub->subscribe($topic, $callbackUrlCreator($subscription['id']));
	if ($result instanceof Exception) {
		return [null, $result];
	}
	
	return [$subscription, null];
}


// Crawl
// Given a URL, recursively fetches rel=prev[ious], calling $callback at each stage.
// returns Exception $error | null on success
// $callback is passed one argument, an array with: url, mf2, response, content, dondocument, parser
// If $callback returns false, crawl is halted.
//
// TODO: make this function check for duplicate content (pass previousResponse on recurse) and halt if duplicated.
function crawl($url, $callback, $timeout=null, $client=null) {
	if ($timeout !== null) {
		$timeStarted = microtime(true);
	} elseif ($timeout <= 0) {
		// Crawl timed out but was successful.
		return null;
	}
	
	if ($client == null) {
		$client = new Guzzle\Http\Client();
	}
	
	$fetch = function ($url) use ($client) {
		try {
			return [$client->get($url)->send(), null];
		} catch (Guzzle\Common\Exception\GuzzleException $e) {
			return [null, $e];
		}
	};
	
	list($resp, $err) = $fetch($url);
	if ($err !== null) {
		return $err;
	}
	
	$parser = new Mf2\Parser($resp->getBody(1), $resp->getEffectiveUrl());
	$mf2 = $parser->parse();
	
	$result = $callback([
		'url' => $resp->getEffectiveUrl(),
		'mf2' => $mf2,
		'response' => $resp,
		'content' => $resp->getBody(1),
		'domdocument' => $parser->doc,
		'parser' => $parser
	]);
	
	$prevUrl = !empty($mf['rels']['prev']) ? $mf['rels']['prev'][0] : !empty($mf['rels']['previous']) ? $mf['rels']['previous'][0] : null;
	
	if ($prevUrl === null or $result === false) {
		return null;
	}
	
	if ($timeout !== null) {
		$timeout = $timeout - (microtime(true) - $timeStarted);
	}
	
	return crawl($prevUrl, $callback, $timeout, $client);
}


function controllers($app, $storage, $authFunction=null, $contentCallbackFunction=null) {
	$subscriptions = $app['controllers_factory'];
	
	$app['subscriptions.callbackurlgenerator'] = $app->protect(function ($id) use ($app) {
		return $app['url_generator']->generate('subscriptions.id.ping', ['id' => $id], true);
	});
	
	if ($authFunction === null) {
		$authFunction = function ($request) { return; };
	}
	
	if ($contentCallbackFunction !== null) {
		$app['dispatcher']->addListener('subscription.ping', $contentCallbackFunction);
	}
	
	// Subscription list.
	$subscriptions->get('/', function (Http\Request $request) use ($app, $storage) {
		$subscriptions = $storage->getSubscriptions();
		
		foreach ($subscriptions as &$subscription) {
			$subscription['url'] = $app['url_generator']->generate('subscriptions.id.get', ['id' => $subscription['id']]);
		}
		
		return $app['render']('subscriptions.html', [
			'subscriptions' => $subscriptions,
			'newSubscriptionUrl' => $app['url_generator']->generate('subscriptions.post'),
			'crawlUrl' => $app['url_generator']->generate('subscriptions.crawl')
		]);
	})->bind('subscriptions')
		->before($authFunction);
	
	
	// Subscription creation.
	$subscriptions->post('/', function (Http\Request $request) use ($app, $storage) {
		if (!$request->request->has('url')) {
			return $app->abort(400, 'Subscription requests must have a url parameter.');
		}
		
		$url = $request->request->get('url');
		$client = $app['http.client'];
		
		list($subscription, $error) = subscribe($storage, $app['push.defaulthub'], $client, $url, $app['subscriptions.callbackurlgenerator']);
		if ($error !== null) {
			$app['logger']->warn('Ran into an error whilst creating a subscription', [
				'exception' => get_class($error),
				'message' => $error->getMessage()
			]);
			$app->abort(400, 'Ran into an error whilst trying to subscribe (see logs)');
		}
				
		return $app->redirect($app['url_generator']->generate('subscriptions.id.get', ['id' => $subscription['id']]));
	})->bind('subscriptions.post')
		->before($authFunction);
	
	
	// Subscription summary, list of recent pings.
	$subscriptions->get('/{id}/', function (Http\Request $request, $id) use ($app, $storage) {
		$subscription = $storage->getSubscription($id);
		if (empty($subscription)) {
			return $app->abort(404, 'No such subscription found!');
		}
		
		$pings = $storage->getPingsForSubscription($id);
		foreach ($pings as &$ping) {
			$ping['url'] = $app['url_generator']->generate('subscriptions.id.ping.datetime', ['id' => $id, 'timestamp' => (new Datetime($ping['datetime']))->format('Y-m-d\TH:i:s')]);
		}
		
		return $app['render']('subscription.html', [
			'subscription' => $subscription,
			'pings' => $pings
		]);
	})->bind('subscriptions.id.get')
		->before($authFunction);
	
	
	// Verification of intent.
	$subscriptions->get('/{id}/ping/', function (Http\Request $request, $id) use ($app, $storage) {
		$subscription = $storage->getSubscription($id);
		if (empty($subscription)) {
			return $app->abort(404, 'No such subscription found!');
		}
		
		// For some reason the periods are converted into underscores by PHP.
		if ($request->query->has('hub_mode')) {
			$p = $request->query->all();
			if ($p['hub_mode'] === $subscription['mode'] and $p['hub_topic'] === $subscription['topic']) {
				$storage->subscriptionIntentVerified($id);
				return $p['hub_challenge'];
			} else {
				return $app->abort(404, 'No such intent!');
			}
		}
		
		return $app->abort(400, 'GET requests to subscription callbacks must be verifications of intent, i.e. have hub.mode parameters');
	});
	
	
	// New content update.
	$subscriptions->post('/{id}/ping/', function (Http\Request $request, $id) use ($app, $storage) {
		$subscription = $storage->getSubscription($id);
		if (empty($subscription)) {
			return $app->abort(404, 'No such subscription found!');
		}
		
		// Compare content with most recent ping, if it exists.
		$latestPing = $storage->getLatestPingForSubscription($id);
		
		if (!empty($latestPing) and $latestPing['content'] == $request->getContent()) {
			$app['logger']->info("Not adding new ping for subscription {$id} as content is the same as previous ping.");
			return '';
		}
		
		$ping = [
			'subscription' => $subscription['id'],
			'content_type' => $request->headers->get('Content-type'),
			'content' => $request->getContent()
		];
		
		$storage->createPing($ping);
		
		$response = new Guzzle\Http\Message\Response(200, $request->headers->all(), $request->getContent());
		$parser = new Mf2\Parser($request->getContent(), $subscription['topic']);
		$event = new EventDispatcher\GenericEvent($response, [
			'url' => $subscription['topic'],
			'mf2' => $mf2,
			'response' => $response,
			'content' => $request->getContent(),
			'domdocument' => $parser->doc,
			'parser' => $parser
		]);
		$app['dispatcher']->dispatch('subscription.ping', $event);
		
		return '';
	})->bind('subscriptions.id.ping');
	
	
	// Individual ping content view.
	$subscriptions->get('/{id}/{timestamp}/', function (Http\Request $request, $id, $timestamp) use ($app, $storage) {
		$ping = $storage->getPing($id, $timestamp);
		if (empty($ping)) {
			$app->abort(404, 'No such ping found!');
		}
		
		if (strstr($ping['content_type'], 'html') !== false) {
			return new Http\Response($ping['content'], 200, ['Content-type' => 'text/plain']);
		} else {
			// Probably a bunch of potential attacks here, but for the moment it’s adequate.
			return new Http\Response($ping['content'], 200, ['Content-type' => $ping['content_type']]);
		}
	})->bind('subscriptions.id.ping.datetime')
		->before($authFunction);
	
	// Crawl(+ensure subscription)
	$subscriptions->post('/crawl/', function (Http\Request $request) use ($app, $storage) {
		$url = $request->request->get('url');
		$client = $app['http.client'];
		
		// Subscribe to URL.
		list($subscription, $error) = subscribe($storage, $app['push.defaulthub'], $client, $url, $app['subscriptions.callbackurlgenerator']);
		if ($error !== null) {
			$app['logger']->warn('Crawl: Subscribing to a URL failed:', [
				'url' => $url,
				'exceptionClass' => get_class($error),
				'message' => $error->getMessage()
			]);
			$app->abort(400, "Subscribing to {$url} failed.");
		}
		
		return new Http\StreamedResponse(function () use ($url, $app) {
			// Recursively fetch $url, dispatcjing the ping event for each page and echoing the page’s URL until no more rel=prev[ious] is found,
			// yields duplicate content, a HTTP error, or some timeout is reached.
			$error = Subscriptions\crawl($url, function ($resource) use ($app) {
				$app['dispatcher']->dispatch('subscription.ping', new EventDispatcher\GenericEvent($resource['response'], $resource));
				echo "{$resource['topic']}\n";
				ob_flush();
				flush();
			}, null, $app['http.client']);
			
			if ($error !== null) {
				$app['logger']->warn('Crawl: Crawling failed', [
					'url' => $url,
					'exceptionClass' => get_class($error),
					'message' => $error->getMessage()
				]);
				$app->abort(500, "Crawling {$url} failed");
			}
		});
	})->bind('subscriptions.crawl')
		->before($authFunction);
	
	return $subscriptions;
}
