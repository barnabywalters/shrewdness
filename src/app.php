<?php

namespace Taproot\Shrewdness;

use Silex\Application;
use Silex\Provider\UrlGeneratorServiceProvider;
use Guzzle;
use Taproot;
use PDO;

$app = new Application();

$app->register(new UrlGeneratorServiceProvider());

$app['db'] = $app->share(function () use ($app) {
	return new PDO($app['db.dsn']);
});

$app['push.defaulthub'] = function () use ($app) {
	return new Taproot\SuperfeedrHub($app['superfeedr.username'], $app['superfeedr.password']);
};

$app['http.client'] = function () use ($app) {
	return new Guzzle\Http\Client();
};

return $app;
