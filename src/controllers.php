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

// If there’s an authentication issue, ensure the user is logged out afterwards.
$app['dispatcher']->addListener('kernel.response', function (HttpKernel\Event\FilterResponseEvent $event) use ($app) {
	if (in_array($event->getResponse()->getStatusCode(), [401, 403])) {
		$app['indieauth']->logoutResponse($event->getResponse());
	}
});


$ensureIsAdmin = function (Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	if ($token === null or $token['me'] != $app['owner.url']) {
		return $app->abort(401, 'Only the site owner may view this page');
	}
};


$ensureIsUser = function (Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	if ($token === null or !file_exists(dataPath(parse_url($token['me'], PHP_URL_HOST)))) {
		return $app->abort(401, 'You must be logged in to view this page.');
	}

	$user = loadJson($token, 'columns');
	if (isset($user['requiresSecureLogin']) and $user['requiresSecureLogin'] === true) {
		if (parse_url($token['me'], PHP_URL_SCHEME) !== 'https') {
			return $app->abort(401, 'You must log in securely, using a URL beginning with https.');
		}
	} else {
		$user['requiresSecureLogin'] = parse_url($token['me'], PHP_URL_SCHEME) === 'https';
		saveJson($token, 'columns', $user);
	}

};


$app->error(function (Exception $e, $code) use ($app) {
	return new Http\Response($e->getMessage(), $code);
});

$app->get('/', function (Http\Request $request) use ($app, $ensureIsUser) {
	$token = $request->attributes->get('indieauth.client.token');
	if ($token !== null) {
		$ensureIsUser($request);

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
					'photo' => ['type' => 'string', 'index' => 'not_analyzed'],
					'logo' => ['type' => 'string', 'index' => 'not_analyzed'],
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

		$columns = loadJson($token, 'columns');
		if ($columns === false) {
			$columns = ['columns' => [[
				'id' => 'feed',
				'sources' => []
			]]];
			saveJson($token, 'columns', $columns);
		}

		foreach ($columns['columns'] as &$column) {
			$column['items'] = fetchColumnItems($app, $column);
		}

		return $app['render']('dashboard.html', [
			'columns' => $columns['columns'],
			'token' => $token,
			'logoutUrl' => $app['url_generator']->generate('logout')
		]);
	} else {
		return $app['render']('index.html', [
			'nextUrl' => $app['url_generator']->generate('homepage', [], true)
		]);
	}
})->bind('homepage');


function fetchColumnItems($app, $column, $from=0, $size=10) {
	/* @var Elasticsearch\Client $es */
	$es = $app['elasticsearch'];

	$query = [
			'index' => 'shrewdness',
			'type' => 'h-entry',
			'body' => [
				'query' => [],
				'sort' => [[
						'published' => ['order' => 'desc']
				]],
				'size' => $size,
				'from' => $from
			]
	];

	if (isset($column['sources'])) {
		$query['body']['query']['terms'] = [
				'topics' => array_reduce($column['sources'], function ($topics, $source) {
					// Match posts found in both http and https topics.
					$plainTopic = removeScheme($source['topic']);
					return array_merge(["https://{$plainTopic}", "http://{$plainTopic}"], $topics);
				}, [])
		];
	} elseif (isset($column['search'])) {
		$query['body']['query']['multi_match'] = [
			'query' => $column['search']['term'],
			'fields' => ['content', 'name^3', 'tags^2', 'location.name', 'author.name']
		];

		if (isset($column['search']['order']) and $column['search']['order'] == 'relevance') {
			$query['body']['sort'] = ['_score'];
		} // Otherwise leave default ordering by published on.
	}

	$results = $es->search($query);
	return array_map(function ($hit) use ($es) {
		$item = $hit['_source'];
		$item['published'] = new DateTime($item['published']);
		foreach (['in-reply-to', 'like-of', 'repost-of'] as $field) {
			$item[$field] = array_map(function ($url) use ($es) {
				$results = $es->search(['index' => 'shrewdness', 'type' => 'h-entry', 'body' => [
					'query' => [
						'term' => [
							'url' => $url
						]
					]
				]]);
				return @($results['hits']['hits'][0]['_source'] ?: $url);
			}, $item[$field]);
		}
		return $item;
	}, $results['hits']['hits']);
}


$app->post('/micropub/', function (Http\Request $request) use ($app) {
	// Currently all micropub postings are new notes
	// Get the current user’s micropub endpoint and access token
	$token = $request->attributes->get('indieauth.client.token');
	$accessToken = $token['access_token'];
	$micropubEndpoint = $token['micropub_endpoint'];

	$data = $request->request->all();
	$data['h'] = 'entry';
	$data['access_token'] = $accessToken;
	$loggableData = $data;
	$loggableData['access_token'] = 'Unlogged string of length ' . strlen($data['access_token']);

	$client = new Guzzle\Http\Client();
	$app['logger']->info('Posting reply to micropub endpoint', [
		'endpoint' => $micropubEndpoint,
		'data' => $data
	]);

	try {
		$resp = $client->post($micropubEndpoint)->addPostFields($data)->send();

		if ($resp->isError()) {
			$app['logger']->warn('Got error whilst posting to micropub endpoint', ['response' => $resp]);
			return $app->json([
					'error' => 'Micropub post failed with error code ' . $resp->getStatus()
			], 500);
		}

		return $app->json([
				'message' => 'Successfully posted to micropub endpoint'
		]);
	} catch (Guzzle\Common\Exception\GuzzleException $e) {
		$app['logger']->warn('Intertubes: micropub POST request failed', [
				'endpoint' => $micropubEndpoint,
				'data' => $data,
				'message' => $e->getMessage()
		]);

		return $app->json([
			'error' => 'Micropub POST request failed!'
		], 500);
	}
})->bind('micropub')
	->before($ensureIsUser);


$app->post('/columns/', function (Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	$columns = loadJson($token, 'columns');

	$newColType = $request->request->get('type', 'subscribe');
	$column = [
		'id' => uniqid(),
		'name' => 'New Column'
	];

	if ($newColType === 'subscribe') {
		$column['sources'] = [];
	} elseif ($newColType === 'search') {
		$column['search'] = [
			'term' => '',
			'order' => 'relevance'
		];
	} else {
		return $app->abort(400, "Unknown column type {$request->request->get('type')} given!");
	}

	$columns['columns'][] = $column;
	saveJson($token, 'columns', $columns);

	$html = $app['render']('column.html', ['column' => $column]);
	return new Http\Response($html, 201, ['Content-length' => strlen($html)]);
})->bind('columns')
	->before($ensureIsUser);


$app->get('/columns/{id}/', function ($id, Http\Request $request) use ($app) {
	// TODO: turn this into a converter which can be applied to multiple handlers.
	$token = $request->attributes->get('indieauth.client.token');
	$columns = loadJson($token, 'columns');
	$column = firstWith($columns['columns'], ['id' => $id]);

	$column['items'] = fetchColumnItems($app, $column, $request->query->get('from', 0), $request->query->get('size', 10));

	$html = $app['render']('column.html', [
		'column' => $column,
		'token' => $token
	]);
	return new Http\Response($html, 200, ['Content-length' => strlen($html)]);
})->bind('column')
	->before($ensureIsUser);


$app->delete('/columns/{id}/', function ($id, Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	$columns = loadJson($token, 'columns');
	$columns['columns'] = array_filter($columns['columns'], function ($column) use ($id) {
		return $column['id'] != $id;
	});
	saveJson($token, 'columns', $columns);

	return new Http\Response('', 200, ['Content-length' => 0]);
})->before($ensureIsUser);


// TODO: no reason to duplicate code between these three handlers. Need to move them both into one column-settings-updating
// handler which can scale to arbitrary future column types.
$app->post('/columns/{id}/', function ($id, Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	$columns = loadJson($token, 'columns');
	$column = firstWith($columns['columns'], ['id' => $id]);

	$column['name'] = $request->request->get('name', $column['name']);

	$columns['columns'] = replaceFirstWith($columns['columns'], ['id' => $id], $column);
	saveJson($token, 'columns', $columns);

	return new Http\Response('', 200, ['Content-length' => 0]);
})->before($ensureIsUser);


$app->post('/columns/{id}/search/', function ($id, Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	$columns = loadJson($token, 'columns');
	$column = firstWith($columns['columns'], ['id' => $id]);

	if (empty($column['search'])) {
		return $app->abort(400, 'Search configuration cannot be saved on a non-search column.');
	}
	
	$column['search']['term'] = $request->request->get('term', $column['search']['term']);
	$column['search']['order'] = $request->request->get('order', $column['search']['order']);

	$columns['columns'] = replaceFirstWith($columns['columns'], ['id' => $id], $column);
	saveJson($token, 'columns', $columns);

	return new Http\Response('', 200, ['Content-length' => 0]);
})->before($ensureIsUser);


$app->post('/columns/{id}/sources/', function ($id, Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	$columns = loadJson($token, 'columns');
	$column = firstWith($columns['columns'], ['id' => $id]);

	$url = $request->request->get('url');
	if ($url === null) {
		return $app->abort(400, 'Provide a ‘url’ parameter to add to sources');
	}

	$url = Authentication\ensureUrlHasHttp($url);

	if ($request->request->get('mode', 'subscribe') === 'subscribe') {
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
		if (saveJson($token, 'columns', $columns) === false) {
			$app['logger']->warn('Failed to save updated columns.json', []);
		}

		// Add post-response crawl task.
		$app['dispatcher']->addListener('kernel.terminate', function (HttpKernel\Event\PostResponseEvent $event) use ($request, $subscription, $app) {
			if ($event->getRequest() !== $request) {
				// Only execute this code after the request it was started from, not other requests.
				return;
			}

			// If this topic isn’t already being crawled, start a crawl, maintaining a cache key to prevent duplicate crawls.
			$crawlingKey = "crawling_{$subscription['topic']}";
			if (!$app['cache']->contains($crawlingKey)) {
				$refreshCache = function ($resource) use ($app, $crawlingKey) {
					$app['logger']->info("Crawling {$resource['url']}", []);
					$app['cache']->save($crawlingKey, true, 10);
				};

				list($subscription, $err) = Subscriptions\subscribeAndCrawl($app, $subscription['topic'], $refreshCache);
				if ($err !== null) {
					$app['logger']->warn('Subscriptions\\subscribeAndCrawl produced an error:', [
							'subscription' => $subscription,
							'error class' => get_class($err),
							'error' => $err
					]);
				}

				$app['cache']->delete($crawlingKey);
			}
		});
	} else {
		// mode === unsubscribe
		$column['sources'] = array_values(array_filter($column['sources'], function ($source) use ($url) {
			return $source['topic'] !== $url;
		}));

		$columns['columns'] = replaceFirstWith($columns['columns'], ['id' => $id], $column);
		if (saveJson($token, 'columns', $columns) === false) {
			$app['logger']->warn('Failed to save updated columns.json', []);
		}

		// TODO: check for usage in other columns and actually unsubscribe from the feed if this was the last reference.
	}

	// Build the HTML to add to the sources list. Explicitly calculating Content-length to satisfy XMLHttpRequest, which
	// stays in “Downloading” mode until the number of bytes received equals Content-length.
	$html = $app['render']('sources.html', ['sources' => $column['sources']], false);
	return new Http\Response($html, 200, ['Content-length' => strlen($html)]);
})->bind('column.sources')
	->before($ensureIsUser);


$app->get('/test/', function (Http\Request $request) use ($app) {
	if (!$request->query->has('url')) {
		return $app['render']('test.html', [
			'column' => null,
			'token' => []
		]);
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
		// Hide topics to keep subscription URLs private.
		$item['topics'] = [];
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
		'url' => $url,
		'token' => []
	]);
});


$app->get('/twitter/list/{user}/{list}/', function ($user, $list, Http\Request $request) use ($app) {
	try {
		$response = $app['http.client']
				->get("https://twitter-activitystreams.appspot.com/{$user}/{$list}/@app/?format=html&access_token_key={$app['twitter.token_key']}&access_token_secret={$app['twitter.token_secret']}")
				->send();
		return $response->getBody(true);
	} catch (Guzzle\Common\Exception\GuzzleException $e) {
		$app->abort(500);
	}
});



// Temporary demo URL for IWC UK 2014
$app->get('/locations/', function (Http\Request $request) use ($app) {
	/** @var $es \Elasticsearch\Client $es */
	$es = $app['elasticsearch'];

	$query = [
		'index' => 'shrewdness',
		'type' => 'h-entry',
		'body' => [
				'query' => ['match_all' => []],
				'sort' => [[
						'published' => ['order' => 'desc']
				]],
				'filter' => [
					'exists' => ['field' => 'location_point']
				],
				'size' => 1000,
				'from' => 0
		]
	];

	$response = $es->search($query);
	$results = array_map(function ($hit) {
		$item = $hit['_source'];
		$item['published'] = new DateTime($item['published']);
		return $item;
	}, $response['hits']['hits']);

	$authorLocations = [];
	foreach ($results as $hEntry) {
		$authorLocations[$hEntry['author']['url']][] = $hEntry;
	}

	return $app['render']('locations.html', [
		'authorLocations' => $authorLocations
	], false);
});


$app->mount('/subscriptions', Subscriptions\controllers($app, $ensureIsAdmin, $app['indexResource']));
$app->mount('/', Authentication\client($app));
