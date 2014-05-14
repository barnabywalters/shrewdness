<?php

namespace Taproot\Shrewdness;

use Symfony\Component\HttpFoundation as Http;
use Guzzle;
use Mf2;
use Taproot;
use Taproot\Subscriptions;
use Taproot\Authentication;

/** @var $app \Silex\Application */

$app->get('/', function (Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	if ($token !== null) {
		return $app['render']('dashboard.html');
	} else {
		return $app['render']('index.html');
	}
})->bind('homepage');

$ensureIsOwner = function (Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	if ($token === null or $token['me'] != $app['owner.url']) {
		return $app->abort(401, 'Only the site owner may view this page');
	}
};

$app->mount('/subscriptions', Subscriptions\controllers($app, $ensureIsOwner, $app['indexResource']));
$app->mount('/', Authentication\client($app));
