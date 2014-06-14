<?php

namespace Taproot\Shrewdness;

use Silex\Application;
use Symfony\Component\HttpFoundation as Http;
use Symfony\Component\HttpKernel;
use Guzzle;
use Mf2;
use Taproot;
use Taproot\Subscriptions;
use Taproot\Authentication;
use Elasticsearch;
use DateTime;
use Exception;

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

		$columns = loadJson('columns');

		return $app['render']('dashboard.html', [
			'columns' => $columns['columns']
		]);
	} else {
		return $app['render']('index.html');
	}
})->bind('homepage');


$app->post('/columns/{id}/sources/', function ($id, Http\Request $request) use ($app) {
	$columns = loadJson('columns');
	$column = firstWith($columns['columns'], ['id' => $id]);

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
	foreach ($column['sources'] as $s) {
		if ($s['topic'] == $source['topic']) {
			$found = true;
			break;
		}
	}

	if (!$found) {
		$column['sources'][] = $source;
	}

	$columns = replaceFirstWith($columns, ['id' => $id], $column);
	saveJson('columns', $columns);

	// Add post-response crawl task.
	$app['dispatcher']->addListener('kernel.terminate', function (HttpKernel\Event\PostResponseEvent $event) use ($request, $s, $app) {
		if ($event->getRequest() !== $request) {
			// Only execute this code after the request it was started from, not other requests.
			return;
		}

		// If this topic isn’t already being crawled, start a crawl, maintaining a cache key to prevent duplicate crawls.
		$crawlingKey = "crawling_{$s['topic']}";
		if (!$app['cache']->contains($crawlingKey)) {
			$refreshCache = function () use ($app, $crawlingKey) {
				$app['cache']->save($crawlingKey, true, 10);
			};

			list($subscription, $err) = Subscriptions\subscribeAndCrawl($app, $s['topic'], $refreshCache);
			if ($err !== null) {
				$app['logger']->warn('Subscriptions\\subscribeAndCrawl produced an error:', [
					'subscription' => $s,
					'error class' => get_class($err),
					'error' => $err
				]);
			}

			$app['cache']->delete($crawlingKey);
		}
	});

	// Build the HTML to add to the sources list. Explicitly calculating Content-length to satisfy XMLHttpRequest, which
	// stays in “Downloading” mode until the number of bytes received equals Content-length.
	$html = $app['render']('sources.html', ['sources' => $column['sources']], false);
	return new Http\Response($html, 200, ['Content-length' => strlen($html)]);
})->bind('column.sources')
	->before($ensureIsOwner);


$app->get('/test/', function (Http\Request $request) use ($app) {
	if (!$request->query->has('url')) {
		return $app['render']('test.html', ['column' => null]);
	}

	$url = Authentication\ensureUrlHasHttp($request->query->get('url'));

	/* @var $resp Guzzle\Http\Message\Response */
	$resp = $app['http.client']->get($url)->send();
	$resource = Subscriptions\contextFromResponse($resp->getBody(true), $resp->getEffectiveUrl(), $resp->getHeaders(), $resp->getEffectiveUrl());
	$indexResourceResult = $app['indexResource']($resource, false);
	$cleansed = array_map(function ($item) {
		$item['published'] = new DateTime($item['published']);
		if (!empty($item['updated'])) {
			try {
				$item['updated'] = new DateTime($item['updated']);
			} catch (Exception $e) {
				// Meh.
			}
		}
		return $item;
	}, $indexResourceResult['feed-parse']['posts']);

	$column = [
		'id' => '_test',
		'name' => 'Test Column',
		'sources' => [],
		'items' => $cleansed
	];

	return $app['render']('test.html', [
		'column' => $column,
		'html' => $resp->getBody(true),
		'mf' => $resource['mf2'],
		'cleansed' => $cleansed,
		'url' => $url
	]);
});

$app->mount('/subscriptions', Subscriptions\controllers($app, $ensureIsOwner, $app['indexResource']));
$app->mount('/', Authentication\client($app));
