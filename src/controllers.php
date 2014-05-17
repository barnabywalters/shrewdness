<?php

namespace Taproot\Shrewdness;

use Symfony\Component\HttpFoundation as Http;
use Symfony\Component\HttpKernel;
use Guzzle;
use Mf2;
use Taproot;
use Taproot\Subscriptions;
use Taproot\Authentication;
use Elasticsearch;

/** @var $app \Silex\Application */

/**
 * Ensure Elasticsearch Index Exists
 * @param Elasticsearch\Client $es
 * @param $indexName
 */
function ensureElasticsearchIndexExists(Elasticsearch\Client $es, $indexName) {
	if (!$es->indices()->exists(['index' => $indexName])) {
		$es->indices()->create(['index' => $indexName]);
	}
}

$ensureIsOwner = function (Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	if ($token === null or $token['me'] != $app['owner.url']) {
		return $app->abort(401, 'Only the site owner may view this page');
	}
};

$app->get('/', function (Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	if ($token !== null) {
		/** @var $es \Elasticsearch\Client $es */
		$es = $app['elasticsearch'];

		ensureElasticsearchIndexExists($es, 'shrewdness');

		$results = $es->search([
			'index' => 'shrewdness',
			'type' => 'column',
			'body' => [
				'query' => [
					'match_all' => []
				]
			]
		]);
		$columns = $results['hits']['hits'];

		return $app['render']('dashboard.html', [
			'columns' => $columns
		]);
	} else {
		return $app['render']('index.html');
	}
})->bind('homepage');


$app->post('/columns/{id}/sources/', function ($id, Http\Request $request) use ($app) {
	/** @var $es \Elasticsearch\Client $es */
	$es = $app['elasticsearch'];

	$column = $es->get([
		'index' => 'shrewdness',
		'type' => 'column',
		'id' => $id
	]);

	$url = $request->request->get('url');
	if ($url === null) {
		return $app->abort(400, 'Provide a ‘url’ parameter to add to sources');
	}

	list($subscription, $err) = Subscriptions\subscribe($app, $url);
	if ($err !== null) {
		$app['logger']->warn('HTTP Error whilst trying to subscribe to a feed', [
			'feed URL' => $url,
			'exception class' => get_class($err),
			'message' => $err->getMessage()
		]);

		return $app->abort(500, "Subscribing to {$url} failed.");
	}

	// From the fetched feed index page, find a profile (if any) and build the source to add to the column definition.
	$mf = $subscription['resource']['mf2'];
	$profile = firstHCard($mf);
	$source = [
			'topic' => $subscription['topic'],
			'profile' => $profile
	];

	$found = false;
	foreach ($column['_source']['sources'] as $s) {
		if ($s['topic'] == $source['topic']) {
			$found = true;
			break;
		}
	}

	if (!$found) {
		$column['_source']['sources'][] = $source;
	}

	$es->index([
		'index' => 'shrewdness',
		'type' => 'column',
		'id' => $column['_id'],
		'body' => $column['_source']
	]);

	// Add post-response crawl task.
	$app['dispatcher']->addListener('kernel.terminate', function (HttpKernel\Event\PostResponseEvent $event) use ($request, $url, $app) {
		if ($event->getRequest() !== $request) {
			// Only execute this code after the request it was started from, not other requests.
			return;
		}

		list($subscription, $err) = Subscriptions\subscribeAndCrawl($app, $url);
	});

	// Build the HTML to add to the sources list. Explicitly calculating Content-length to satisfy XMLHttpRequest, which
	// stays in “Downloading” mode until the number of bytes received equals Content-length.
	$html = $app['render']('sources.html', ['sources' => $column['_source']['sources']], false);
	return new Http\Response($html, 200, ['Content-length' => strlen($html)]);
})->bind('column.sources')
	->before($ensureIsOwner);

$app->mount('/subscriptions', Subscriptions\controllers($app, $ensureIsOwner, $app['indexResource']));
$app->mount('/', Authentication\client($app));
