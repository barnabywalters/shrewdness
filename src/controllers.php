<?php

namespace Taproot\Shrewdness;

use Symfony\Component\HttpFoundation as Http;
use Guzzle;
use Mf2;
use Exception;

$app->get('/', function (Http\Request $request) use ($app) {
	return 'Hello world!';
});

$app->get('/subscriptions/', function (Http\Request $request) use ($app) {
	$subscriptions = $app['db']->query('SELECT * FROM shrewdness_subscriptions;')->fetchAll();
	
	foreach ($subscriptions as $subscription) {
		$subscription['url'] = $app['url_generator']->generate('subscriptions.id.get', ['id' => $subscription['id']]);
	}
	
	return render('subscriptions.html', [
		'subscriptions' => $subscriptions,
		'newSubscriptionUrl' => $app['url_generator']->generate('subscriptions.post')
	]);
});

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
		return [null, $e];
	}
	
	list($topic, $hubs) = pushLinksForResponse($resp);
	
	// If hub exists, subscribe at that hub.
	if (!empty($hubs)) {
		$hubUrl = $hubs[0];
		$hub = new PushHub($hubUrl);
	} else {
		$hub = $app['push.defaulthub'];
	}
	
	$subscription = [
		'topic' => $topic,
		'hub' => $hub->getUrl()
	];
	
	$existingSubscription = $app['db']->prepare('SELECT * FROM shrewdness_subscriptions WHERE topic = :topic AND hub = :hub;');
	$existingSubscription->execute($subscription);
	if ($existingSubscription->rowCount() !== 0) {
		$s = $existingSubscription->fetch();
		if ($s['mode'] !== 'subscribe') {
			$app['db']->prepare("UPDATE shrewdness_subscriptions SET mode='subscribe' WHERE id = :id;")->execute($s);
		}
	} else {
		$app['db']->prepare('INSERT INTO shrewdness_subscriptions (topic, hub) VALUES (:topic, :hub);')->execute($subscription);
		$subscription = $existingSubscription->execute($subscription);
	}
	
	// Regardless of the state of the database beforehand, $subscription now has an ID and a mode of “subscribe”.
	
	$result = $hub->subscribe($topic, $app['url_generator']->generate('subscriptions.id.ping', ['id' => $subscription['id']], true));
	if ($result instanceof Exception) {
		//return $app->abort('Exception when creating a subscription')
		throw $result;
	}
	
	return $app->redirect($app['url_generator']->generate('subscriptions.id.get', ['id' => $subscription['id']]));
})->bind('subscriptions.post');


$app->get('/subscriptions/{id}/', function (Http\Request $request, $id) use ($app) {
	$subscription = $app['db']->query("SELECT * FROM shrewdness_subscriptions WHERE id = {$app['db']->quote($id)}")->fetch();
	if (empty($subscription)) {
		return $app->abort(404, 'No such subscription found!');
	}
	
	return render('subscription.html', [
		'subscription' => $subscription
	]);
})->bind('subscriptions.id.get');


$app->post('/subscriptions/{id}/ping/', function (Http\Request $request, $id) use ($app) {
	$subscription = $app['db']->query("SELECT * FROM shrewdness_subscriptions WHERE id = {$app['db']->quote($id)}")->fetch();
	if (empty($subscription)) {
		return $app->abort(404, 'No such subscription found!');
	}
	
	// If this is a verification of intent, deal with it.
	if ($request->query->has('hub.mode')) {
		$p = $request->query->all();
		if ($p['hub.mode'] === $subscription['mode'] and $p['hub.mode'] === $subscription['mode'] and $p['topic'] === $subscription['topic']) {
			$app['db']->exec("UPDATE shrewdness_subscriptions SET intent_verified = true WHERE id = {$app['db']->quote($id)}");
			return $p['hub.challenge'];
		} else {
			return $app->abort(404, 'No such intent!');
		}
	}
	
	// Otherwise, create a new ping row and dispatch event.
	$insertPing = $app['db']->prepare('INSERT INTO shrewdness_pings (subscription, content_type, content) VALUES (:subscription, :content_type, :content)');
	$ping = [
		'subscription' => $subscription['id'],
		'content_type' => $request->getContentType(),
		'content' => $request->getContent()
	];
	$insertPing->execute($ping);
	
	$app['dispatcher']->dispatch('subscription.ping');
	
	return '';
})->bind('subscriptions.id.ping');
