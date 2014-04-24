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
		return $this->db->query("SELECT * FROM {$this->prefix}pings WHERE subscription = {$this->db->quote($id)} ORDER BY datetime DESC LIMIT {$this->db->quote($offset)}, {$this->db->quote($offset)};")->fetchAll();
	}
	
	public function subscriptionIntentVerified($id) {
		$this->db->exec("UPDATE {$this->prefix}subscriptions SET intent_verified = 1 WHERE id = {$this->db->quote($id)};");
	}
	
	public function getLatestPingForSubscription($id) {
		return $this->db->query("SELECT * FROM {$this->prefix}pings WHERE subscription = {$this->db->quote($id)} ORDER BY datetime DESC LIMIT 1;")->fetch();
	}
	
	public function createPing(array $ping) {
		$insertPing = $this->db->prepare('INSERT INTO {$this->prefix}pings (subscription, content_type, content) VALUES (:subscription, :content_type, :content);');
		$insertPing->execute($ping);
	}
	
	public function getPing($subscriptionId, $timestamp) {
		$fetchPing = $this->db->prepare("SELECT * FROM {$this->prefix}pings WHERE subscription = :subscription AND datetime = :timestamp;");
		$fetchPing->execute(['subscription' => $id, 'timestamp' => $timestamp]);
		return $fetchPing->fetch();
	}
}

function controllers($app, $storage, $authFunction=null) {
	$subscriptions = $app['controllers_factory'];
	
	if ($authFunction === null) {
		$authFunction = function ($request) { return; };
	}
	
	// Subscription list.
	$subscriptions->get('/', function (Http\Request $request) use ($app, $storage) {
		$subscriptions = $storage->getSubscriptions();
		
		foreach ($subscriptions as &$subscription) {
			$subscription['url'] = $app['url_generator']->generate('subscriptions.id.get', ['id' => $subscription['id']]);
		}
		
		return $app['render']('subscriptions.html', [
			'subscriptions' => $subscriptions,
			'newSubscriptionUrl' => $app['url_generator']->generate('subscriptions.post')
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
		
		$app['logger']->info('Subscription: discovered links', [
			'topic' => $url,
			'links' => $links
		]);
		
		$hub = empty($hubs) ? $app['push.defaulthub'] : new Taproot\PushHub($hubs[0]);
		
		$subscription = $storage->createSubscription($topic, $hub->getUrl());
		
		// Regardless of the state of the database beforehand, $subscription now exists, has an ID and a mode of “subscribe”.
		
		$result = $hub->subscribe($topic, $app['url_generator']->generate('subscriptions.id.ping', ['id' => $subscription['id']], true));
		if ($result instanceof Exception) {
			//return $app->abort('Exception when creating a subscription')
			$app['logger']->error('Subscription: subscription POST request to hub failed', [
				'hub' => (string) $hub,
				'exception' => $result,
				'content' => $result->getResponse()->getBody(true)
			]);
			throw $result;
		}
		
		return $app->redirect($app['url_generator']->generate('subscriptions.id.get', ['id' => $subscription['id']]));
	})->bind('subscriptions.post');
	
	
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
		
		$app['dispatcher']->dispatch('subscription.ping');
		
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
	
	return $subscriptions;
}
