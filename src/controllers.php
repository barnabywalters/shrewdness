<?php

namespace Taproot\Shrewdness;

use Symfony\Component\HttpFoundation as Http;
use Guzzle;
use Mf2;
use Exception;
use Taproot;
use DateTime;

$app->get('/', function (Http\Request $request) use ($app) {
	return 'Hello world!';
})->bind('homepage');

$app->post('/crawl/', function (Http\Request $request) use ($app) {
	$url = $request->request->get('url');
	
	// Check for existing subscription — if there’s already one then do nothing.
	
	// Subscribe to URL with $app['indexResource'] as the callback.
	
	// Recursively fetch $url, applying $app['indexResource'] to each page until no more rel=prev[ious] is found,
	// yields duplicate content, a HTTP error, or some timeout is reached.
})->bind('crawl');

$ensureIsOwner = function (Http\Request $request) use ($app) {
	if (!$request->attributes->has('me') or $request->attributes->get('me') != $app['owner.url']) {
		$app->abort(401, 'Unauthorized');
	}
};

$app->mount('/subscriptions', Subscriptions\controllers($app, $app['subscriptionstorage']));

// Authentication
$app->before(function (Http\Request $request) use ($app) {
	if ($request->query->has('token')) {
		$client = new Guzzle\Http\Client($app['indieauth.url']);
		try {
			$response = $client->get('session?token=' . $request->query->get('token'))->send();
			$data = json_decode($response->getBody());
			$url = $data->me;
			$request->attributes->set('me', $url);
		} catch (Guzzle\Common\Exception\GuzzleException $e) {
			$app->abort(500, 'Authenticating user with indieauth.com failed: ' . $e->getMessage());
		}
	}
});

// Remember-me cookie handler.
$app->after(function (Http\Request $request, Http\Response $response) use ($app) {
	if ($request->cookies->has($app['rememberme.cookiename']) or !$request->attributes->has('user'))
		return;
	
	$user = $request->attributes->get('user');
	$expiry = time() + $app['rememberme.cookielifetime'];
	
	$cookieVal = $app['encryption']->encrypt($user['url']);
	$cookie = new Http\Cookie($app['rememberme.cookiename'], $cookieVal, $expiry);
	$response->headers->setCookie($cookie);
});