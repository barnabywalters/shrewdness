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
});

$app->mount('/subscriptions', Subscriptions\controllers($app));
