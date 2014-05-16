<?php

namespace Taproot\Shrewdness;

use Symfony\Component\HttpFoundation as Http;
use Guzzle;
use Mf2;
use Taproot;
use Taproot\Subscriptions;
use Taproot\Authentication;
use Elasticsearch;

function ensureElasticsearchIndexExists(Elasticsearch\Client $es, $indexName) {
	if (!$es->indices()->exists(['index' => $indexName])) {
		$es->indices()->create(['index' => $indexName]);
	}
}

/** @var $app \Silex\Application */

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

$ensureIsOwner = function (Http\Request $request) use ($app) {
	$token = $request->attributes->get('indieauth.client.token');
	if ($token === null or $token['me'] != $app['owner.url']) {
		return $app->abort(401, 'Only the site owner may view this page');
	}
};

$app->mount('/subscriptions', Subscriptions\controllers($app, $ensureIsOwner, $app['indexResource']));
$app->mount('/', Authentication\client($app));
