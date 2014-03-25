<?php

use Silex\Application;

$app = new Application();

$app['db'] = $app->share(function () use ($app) {
	return new PDO($app['db.dsn']);
});

return $app;
