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
function ensureElasticsearchIndexExists(Elasticsearch\Client $es, $indexName, $mappings=[]) {
	if (!$es->indices()->exists(['index' => $indexName])) {
		$es->indices()->create([
			'index' => $indexName,
			'body' => [
				'mappings' => $mappings
			]
		]);
	}
}

$ensureIsOwner = function (Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	if ($token === null or $token['me'] != $app['owner.url']) {
		return $app->abort(401, 'Only the site owner may view this page');
	}
};

$app->error(function (Exception $e, $code) use ($app) {
	return new Http\Response($e->getMessage(), $code);
});

$app->get('/', function (Http\Request $request) use ($app, $ensureIsOwner) {
	$token = $request->attributes->get('indieauth.client.token');
	if ($token !== null) {
		$ensureIsOwner($request);

		/** @var $es \Elasticsearch\Client $es */
		$es = $app['elasticsearch'];

		ensureElasticsearchIndexExists($es, 'shrewdness', [
			'h-entry' => [
				'properties' => [
					'author' => [
						'properties' => [
							'name' => ['type' => 'string'],
							'url' => ['type' => 'string', 'index' => 'not_analyzed'],
							'photo' => ['type' => 'string', 'index' => 'not_analyzed'],
						]
					],
					'content' => ['type' => 'string', 'analyzer' => 'english'],
					'display_content' => ['type' => 'string'],
					'in-reply-to' => ['type' => 'string', 'index' => 'not_analyzed'],
					'like-of' => ['type' => 'string', 'index' => 'not_analyzed'],
					'repost-of' => ['type' => 'string', 'index' => 'not_analyzed'],
					'name' => ['type' => 'string'],
					'published' => ['type' => 'date', 'format' => 'dateOptionalTime'],
					'published_utc' => ['type' => 'date', 'format' => 'dateOptionalTime'],
					'text' => ['type' => 'string', 'index' => 'no'],
					'topics' => ['type' => 'string', 'index' => 'not_analyzed'],
					'tags' => ['type' => 'string', 'index' => 'not_analyzed'],
					'type' => ['type' => 'string', 'index' => 'not_analyzed'],
					'url' => ['type' => 'string', 'index' => 'not_analyzed'],
					'location' => [
						'properties' => [
							'name' => ['type' => 'string'],
							'latitude' => ['type' => 'float', 'index' => 'no'],
							'longitude' => ['type' => 'float', 'index' => 'no'],
							'post-office-box' => ['type' => 'string'],
							'extended-address' => ['type' => 'string'],
							'street-address' => ['type' => 'string'],
							'locality' => ['type' => 'string'],
							'region' => ['type' => 'string'],
							'postal-code' => ['type' => 'string'],
							'country-name' => ['type' => 'string'],
							'url' => ['type' => 'string', 'index' => 'not_analyzed'],
							'photo' => ['type' => 'string', 'index' => 'not_analyzed'],
						]
					],
					'location_point' => ['type' => 'geo_point']
				]
			]
		]);

		$columns = loadJson('columns');
		if ($columns === false) {
			$columns = ['columns' => [[
				'id' => 'feed',
				'sources' => []
			]]];
			saveJson('columns', $columns);
		}

		foreach ($columns['columns'] as &$column) {
			$results = $es->search([
				'index' => 'shrewdness',
				'type' => 'h-entry',
				'body' => [
					'query' => [
						'terms' => [
							'topics' => array_map(function ($source) {
								return $source['topic'];
							}, $column['sources'])
						]
					],
					'sort' => [[
						'published' => ['order' => 'desc']
					]],
					'size' => 50
				]
			]);
			$column['items'] = array_map(function ($hit) {
				$item = $hit['_source'];
				$item['published'] = new DateTime($item['published']);
				return $item;
			}, $results['hits']['hits']);
		}

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
	$profile = authorHCard($mf, $url);
	$source = [
			'topic' => $subscription['topic'],
			'profile' => $profile
	];

	$found = false;
	foreach ($column['sources'] as $i => $s) {
		if ($s['topic'] == $source['topic']) {
			$found = true;
			// Update any profile details from re-subscribing.
			$column['sources'][$i] = $source;
			break;
		}
	}
	if (!$found) {
		$column['sources'][] = $source;
	}

	$columns['columns'] = replaceFirstWith($columns['columns'], ['id' => $id], $column);
	if (saveJson('columns', $columns) === false) {
		$app['logger']->warn('Failed to save updated columns.json', []);
	}

	// Add post-response crawl task.
	$app['dispatcher']->addListener('kernel.terminate', function (HttpKernel\Event\PostResponseEvent $event) use ($request, $s, $app) {
		if ($event->getRequest() !== $request) {
			// Only execute this code after the request it was started from, not other requests.
			return;
		}

		// If this topic isn’t already being crawled, start a crawl, maintaining a cache key to prevent duplicate crawls.
		$crawlingKey = "crawling_{$s['topic']}";
		if (!$app['cache']->contains($crawlingKey)) {
			$refreshCache = function ($resource) use ($app, $crawlingKey) {
				$app['logger']->info("Crawling {$resource['url']}", []);
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
