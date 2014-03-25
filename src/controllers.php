<?php

namespace Taproot\Shrewdness;

use Symfony\Component\HttpFoundation as Http;

$app->get('/', function (Http\Request $request) use ($app) {
	return 'Hello world!';
});

$app->get('/subscriptions/', function (Http\Request $request) use ($app) {
	
});

$app->post('/subscriptions/', function (Http\Request $request) use ($app) {
	
});
