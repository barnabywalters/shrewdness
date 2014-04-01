<?php

namespace Taproot\Shrewdness;

use Symfony\Component\HttpFoundation as Http;
use Guzzle;
use Mf2;
use Exception;
use Taproot;

$app->get('/', function (Http\Request $request) use ($app) {
	return 'Hello world!';
});


// Subscription list.
$app->get('/subscriptions/', function (Http\Request $request) use ($app) {
	$subscriptions = $app['db']->query('SELECT * FROM shrewdness_subscriptions;')->fetchAll();
	
	foreach ($subscriptions as &$subscription) {
		$subscription['url'] = $app['url_generator']->generate('subscriptions.id.get', ['id' => $subscription['id']]);
	}
	
	return render('subscriptions.html', [
		'subscriptions' => $subscriptions,
		'newSubscriptionUrl' => $app['url_generator']->generate('subscriptions.post')
	]);
});


// Subscription creation.
$app->post('/subscriptions/', function (Http\Request $request) use ($app) {
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
	
	$subscription = [
		'topic' => $topic,
		'hub' => $hub->getUrl()
	];
	
	$existingSubscription = $app['db']->prepare('SELECT * FROM shrewdness_subscriptions WHERE topic = :topic AND hub = :hub;');
	$existingSubscription->execute($subscription);
	if ($existingSubscription->rowCount() !== 0) {
		$subscription = $existingSubscription->fetch();
		if ($subscription['mode'] !== 'subscribe') {
			$app['db']->prepare("UPDATE shrewdness_subscriptions SET mode='subscribe' WHERE id = :id;")->execute($subscription);
		}
	} else {
		$app['db']->prepare('INSERT INTO shrewdness_subscriptions (topic, hub) VALUES (:topic, :hub);')->execute($subscription);
		$existingSubscription->execute($subscription);
		$subscription = $existingSubscription->fetch();
	}
	
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
$app->get('/subscriptions/{id}/', function (Http\Request $request, $id) use ($app) {
	$subscription = $app['db']->query("SELECT * FROM shrewdness_subscriptions WHERE id = {$app['db']->quote($id)};")->fetch();
	if (empty($subscription)) {
		return $app->abort(404, 'No such subscription found!');
	}
	
	$pings = $app['db']->query("SELECT * FROM shrewdness_pings WHERE subscription = {$app['db']->quote($id)} ORDER BY datetime DESC LIMIT 20;")->fetchAll();
	foreach ($pings as &$ping) {
		$ping['url'] = $app['url_generator']->generate('subscriptions.id.ping.datetime', ['id' => $id, 'timestamp' => (new Datetime($ping['datetime']))->format('Y-m-d\TH:i:s')]);
	}
	
	return render('subscription.html', [
		'subscription' => $subscription,
		'pings' => $pings
	]);
})->bind('subscriptions.id.get');


// Verification of intent.
$app->get('/subscriptions/{id}/ping/', function (Http\Request $request, $id) use ($app) {
	$subscription = $app['db']->query("SELECT * FROM shrewdness_subscriptions WHERE id = {$app['db']->quote($id)};")->fetch();
	if (empty($subscription)) {
		return $app->abort(404, 'No such subscription found!');
	}
	
	// For some reason the periods are converted into underscores by PHP.
	if ($request->query->has('hub_mode')) {
		$p = $request->query->all();
		if ($p['hub_mode'] === $subscription['mode'] and $p['hub_topic'] === $subscription['topic']) {
			$app['db']->exec("UPDATE shrewdness_subscriptions SET intent_verified = 1 WHERE id = {$app['db']->quote($id)};");
			return $p['hub_challenge'];
		} else {
			return $app->abort(404, 'No such intent!');
		}
	}
	
	return $app->abort(400, 'GET requests to subscription callbacks must be verifications of intent, i.e. have hub.mode parameters');
});


// New content update.
$app->post('/subscriptions/{id}/ping/', function (Http\Request $request, $id) use ($app) {
	$subscription = $app['db']->query("SELECT * FROM shrewdness_subscriptions WHERE id = {$app['db']->quote($id)};")->fetch();
	if (empty($subscription)) {
		return $app->abort(404, 'No such subscription found!');
	}
	
	$insertPing = $app['db']->prepare('INSERT INTO shrewdness_pings (subscription, content_type, content) VALUES (:subscription, :content_type, :content);');
	$ping = [
		'subscription' => $subscription['id'],
		'content_type' => $request->headers->get('Content-type'),
		'content' => $request->getContent()
	];
	$insertPing->execute($ping);
	
	$app['dispatcher']->dispatch('subscription.ping');
	
	return '';
})->bind('subscriptions.id.ping');


// Individual ping content view.
$app->get('/subscriptions/{id}/{timestamp}/', function (Http\Request $request, $id, $timestamp) use ($app) {
	$fetchPing = $app['db']->prepare("SELECT * FROM shrewdness_pings WHERE subscription = :subscription AND datetime = :timestamp;");
	$fetchPing->execute(['subscription' => $id, 'timestamp' => $timestamp]);
	$ping = $fetchPing->fetch();
	if (empty($ping)) {
		$app->abort(404, 'No such ping found!');
	}
	
	if (strstr($ping['content_type'], 'html') !== false) {
		return new Http\Response($ping['content'], 200, ['Content-type' => 'text/plain']);
	} else {
		// Probably a bunch of potential attacks here, but for the moment it’s adequate.
		return new Http\Response($ping['content'], 200, ['Content-type' => $ping['content_type']]);
	}
})->bind('subscriptions.id.ping.datetime');
