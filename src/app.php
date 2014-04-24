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
	return new PDO($app['db.dsn'], $app['db.username'], $app['db.password'], $app['db.options']);
});

$app['subscriptionstorage'] = $app->share(function () use ($app) {
	return new Subscriptions\PdoSubscriptionStorage($app['db']);
});

$app['push.defaulthub'] = function () use ($app) {
	return new Taproot\SuperfeedrHub($app['superfeedr.username'], $app['superfeedr.password']);
};

$app['http.client'] = function () use ($app) {
	return new Guzzle\Http\Client();
};

$app['render'] = $app->protect(function ($template, $__templateData=array()) {
	$__basedir = __DIR__;
	$render = function ($__path, $__templateData) use ($__basedir) {
		$render = function ($template, $data) use ($__basedir) {
			return render($__basedir, $template, $data);
		};
		ob_start();
		extract($__templateData);
		unset($__templateData);
		include $__basedir . '/../templates/' . $__path . '.php';
		return ob_get_clean();
	};
	return $render($template, $__templateData);
});

$app['indexResource'] = $app->protect(function ($resource) use ($app) {
	// Find all links not tagged with rel=nofollow.
	
	// For each link, ensure there is a row linking this authority to the links’s authority/origin (find good name here).
	// Where “authority” ≈ domain, with some special cases for silos like Twitter.
	// E.G. authority of http://waterpigs.co.uk/notes/1000 is waterpigs.co.uk
	// authority of https://twitter.com/aaronpk/status/1234567890 is twitter.com/aaronpk
	// Also note relation(s), derived from mf2/rel values, store those as space-separated.
});

return $app
