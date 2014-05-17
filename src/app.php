<?php

namespace Taproot\Shrewdness;

use Silex\Application;
use Silex\Provider\UrlGeneratorServiceProvider;
use Guzzle;
use Taproot;
use Taproot\Subscriptions;
use PDO;
use Illuminate\Encryption\Encrypter;
use Elasticsearch;

$app = new Application();

$app->register(new UrlGeneratorServiceProvider());

$app['db'] = $app->share(function () use ($app) {
	return new PDO($app['db.dsn'], $app['db.username'], $app['db.password'], $app['db.options']);
});

$app['subscriptions.storage'] = $app->share(function () use ($app) {
	return new Subscriptions\PdoSubscriptionStorage($app['db']);
});

$app['subscriptions.defaulthub'] = function () use ($app) {
	return new Subscriptions\SuperfeedrHub($app['superfeedr.username'], $app['superfeedr.password']);
};

$app['http.client'] = function () use ($app) {
	return new Guzzle\Http\Client();
};

$app['encryption'] = function () use ($app) {
	return new Encrypter($app['encryption.secret']);
};

$app['elasticsearch'] = $app->share(function () use ($app) {
	return new Elasticsearch\Client();
});

$app['render'] = $app->protect(function ($template, $__templateData = array(), $pad = true) {
	$__basedir = __DIR__;

	$result = renderTemplate($__basedir, $template, $__templateData);

	if ($pad) {
		$out = array(
				renderTemplate($__basedir, 'header.html', $__templateData),
				$result,
				renderTemplate($__basedir, 'footer.html', $__templateData)
		);
		return implode('', $out);
	} else {
		return $result;
	}
});

$app['indexResource'] = $app->protect(function ($resource) use ($app) {
	$app['logger']->info('Indexing Resource', [
		'resource' => $resource
	]);
	
	// Archive the response
	
	// Feed Reader Subscription
	// If there are h-entries on the page, for each of them:
	// * use comment-presentation algorithm to clean up, index those values for result presentation
	// * index full content as well, for searching
	// If there are no h-entries
	// * Index the page as an ordinary webpage
	
	
	// Anti-spam measures
	// Find all links not tagged with rel=nofollow.
	
	// For each link, ensure there is a row linking this authority to the links’s authority/origin (find good name here).
	// Where “authority” ≈ domain, with some special cases for silos like Twitter.
	// E.G. authority of http://waterpigs.co.uk/notes/1000 is waterpigs.co.uk
	// authority of https://twitter.com/aaronpk/status/1234567890 is twitter.com/aaronpk
	// Also note relation(s), derived from mf2/rel values, store those as space-separated.
});

return $app;
