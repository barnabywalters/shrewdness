<?php

namespace Taproot\Shrewdness;

use Symfony\Component\HttpFoundation as Http;

$app->get('/', function (Http\Request $request) use ($app) {
	return 'Hello world!';
});
