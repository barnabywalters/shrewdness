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
use Doctrine\Common\Cache;
use IndieWeb\comments;
use HTMLPurifier, HTMLPurifier_Config;
use BarnabyWalters\Mf2 as M;
use Mf2;
use DateTime;
use DateTimeZone;
use Exception;

$app = new Application();

$app->register(new UrlGeneratorServiceProvider());

$app['db'] = $app->share(function () use ($app) {
	return new PDO($app['db.dsn'], $app['db.username'], $app['db.password'], $app['db.options']);
});

$app['subscriptions.storage'] = $app->share(function () use ($app) {
	return new Subscriptions\PdoSubscriptionStorage($app['db'], 'shrewdness_');
});

$app['subscriptions.defaulthub'] = function () use ($app) {
	return new Subscriptions\SuperfeedrHub($app['superfeedr.username'], $app['superfeedr.password']);
};

$app['http.client'] = function () use ($app) {
	$client = new Guzzle\Http\Client();
	$client->setUserAgent('Shrewdness (Guzzle) http://indiewebcamp.com/Shrewdness');
	return $client;
};

$app['encryption'] = function () use ($app) {
	return new Encrypter($app['encryption.secret']);
};

$app['elasticsearch'] = $app->share(function () use ($app) {
	return new Elasticsearch\Client();
});

$app['cache'] = $app->share(function () use ($app) {
	return new Cache\ApcCache();
});

$app['archive'] = $app->share(function () use ($app) {
	return new Taproot\Archive(__DIR__ . '/../data/archive/');
});

$app['purifier'] = function () use ($app) {
	$config = HTMLPurifier_Config::createDefault();
	return new HTMLPurifier($config);
};

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


function processHEntry($hEntry, $mf, $url, $resolveRelationships=true, Guzzle\Http\ClientInterface $client=null, $purifier=null) {
	if ($client === null) {
		$client = new Guzzle\Http\Client();
	}

	if ($purifier === null) {
		$purifier = function ($value) { return $value; };
	}

	// Use comment-presentation algorithm to clean up.
	$cleansed = comments\parse($hEntry);
	$referencedPosts = [];
	$referencedPostUrls = []; // Used internally to keep track of what referenced posts have been processed already.

	$indexedContent = M\getPlaintext($hEntry, 'content', $cleansed['text']);

	$displayContent = $purifier(M\getHtml($hEntry, 'content'));

	$cleansed['content'] = $indexedContent;
	$cleansed['display_content'] = $displayContent;

	// Handle all datetime cases, as per http://indiewebcamp.com/h-entry#How_to_consume_h-entry
	try {
		$published = new DateTime($cleansed['published']);
		$utcPublished = clone $published;
		$utcPublished->setTimezone(new DateTimeZone('UTC'));
	} catch (Exception $e) {
		$published = $utcPublished = false;
	}

	$inTheFuture = $utcPublished > new DateTime(null, new DateTimeZone('UTC'));

	// DateTime() accepts “false” as a constructor param for some reason.
	if ((!$published and !$cleansed['published']) or ($utcPublished > new DateTime(null, new DateTimeZone('UTC')))) {
		// If there’s absolutely no datetime, our best guess has to be “now”.
		// Additional heuristics could be used in the bizarre case of having a feed where an item without datetime is
		// published in between two items with datetimes, allowing us to guess the published datetime is between the two,
		// but until that actually happens it’s not worth coding for.
		$cleansed['published'] = gmdate('c');
		$utcPublished = new DateTime(null, new DateTimeZone('UTC'));
	} else {
		// “published” is given and parses correctly, into $published.
		// Currently it’s not trivial to figure out if a given datetime is floating or not, so assume that the timezone
		// given here is correct for the moment. When this can be determined, follow http://indiewebcamp.com/datetime#implying_timezone_from_webmentions
	}

	// Store a string representation of published to be indexed+queried upon.
	$cleansed['published_utc'] = $utcPublished->format(DateTime::W3C);

	if (M\hasProp($hEntry, 'photo')) {
		$cleansed['photo'] = $purifier(M\getHtml($hEntry, 'photo'));
	}

	if (M\hasProp($hEntry, 'logo')) {
		$cleansed['logo'] = $purifier(M\getHtml($hEntry, 'logo'));
	}

	// For every post this post has a relation (in-reply-to, repost-of, like-of etc.), fetch and resolve that URL,
	// index it as it’s own post (if it doesn’t already exist) and store only a reference to it here.
	$references = [
		'in-reply-to' => [],
		'like-of' => [],
		'repost-of' => []
	];

	foreach ($references as $relation => $_) {
		$refUrls = [];
		// These will be feed pages not permalink pages so cannot check rels, only microformats properties.
		if (M\hasProp($hEntry, $relation)) {
			foreach ($hEntry['properties'][$relation] as $value) {
				if (is_string($value)) {
					$refUrls[] = $value;
				} elseif (is_array($value) and isset($value['html'])) {
					// e-* properties unlikely to be URLs but try all the same.
					$refUrls[] = $value['value'];
				} elseif (M\isMicroformat($value)) {
					if (M\hasProp($value, 'url')) {
						$refUrls[] = M\getProp($value, 'url');
					} elseif (M\hasProp($value, 'uid')) {
						$refUrls[] = M\getProp($value, 'uid');
					}
				} else {
					// If this happens, the microformats parsing spec has changed. Currently do nothing as we don’t know how to interpret this.
				}
			}
		}

		if ($resolveRelationships) {
			foreach ($refUrls as $refUrl) {
				try {
					$resp = $client->get($refUrl)->send();
					$refResolvedUrl = $resp->getEffectiveUrl();
					$refMf = Mf2\parse($resp->getBody(1), $refResolvedUrl);
					$refHEntries = M\findMicroformatsByType($refMf, 'h-entry');
					$relatedUrl = $refResolvedUrl;
					if (count($refHEntries) > 0) {
						$refHEntry = $refHEntries[0];
						$refSearchUrl = M\hasProp($refHEntry, 'url') ? M\getProp($refHEntry, 'url') : $refResolvedUrl;
						if (!in_array($refSearchUrl, $referencedPostUrls)) {
							list($refCleansed, $_) = processHEntry($refHEntry, $refMf, $refResolvedUrl, false, $client, $purifier);
							$referencedPosts[] = $refCleansed;
							$referencedPostUrls[] = $refSearchUrl;
							$relatedUrl = $refSearchUrl;
						}
					}

					$references[$relation][] = $relatedUrl;
				} catch (Guzzle\Common\Exception\GuzzleException $e) {
					$references[$relation][] = $refUrl;
				}
			}
		} else {
			// If we’re not resolving relationships, the most accurate data we have is the data given already.
			$references[$relation] = $refUrls;
		}


		// Now we have the best possible list of URLs, attach it to $cleansed.
		$cleansed[$relation] = array_unique($references[$relation]);
	}

	if (!M\hasProp($hEntry, 'author')) {
		// No authorship data given, we need to find the author!
		// TODO: proper /authorship implementation.
		// TODO: wrap proper /authorship implementation in layer which does purification, simplification, fallback.
		$potentialAuthor = M\getAuthor($hEntry, $mf, $url);

		if ($potentialAuthor !== null) {
			$cleansed['author'] = flattenHCard($potentialAuthor, $url);
		} elseif (!empty($mf['rels']['author'])) {
			// TODO: look in elasticsearch index for a person with the first rel-author URL then fall back to fetching.

			// Fetch the first author URL and look for an h-card there.
			$relAuthorMf = Mf2\fetch($mf['rels']['author'][0]);
			$relAuthorHCards = M\findMicroformatsByType($relAuthorMf, 'h-card');
			if (count($relAuthorHCards)) {
				$cleansed['author'] = flattenHCard($relAuthorHCards[0], $url);
			}
		}

		// If after all that there’s still no authorship data, fake some.
		if ($cleansed['author']['name'] === false) {
			$cleansed['author'] = flattenHCard(['properties' => []], $url);
			try {
				$response = $client->head("{$cleansed['author']['url']}/favicon.ico")->send();
				if (strpos($response->getHeader('content-type'), 'image') !== false) {
					// This appears to be a valid image!
					$cleansed['author']['photo'] = $response->getEffectiveUrl();
				}
			} catch (Guzzle\Common\Exception\GuzzleException $e) {
				// No photo fallback could be found.
			}
		}
	}

	// TODO: this will be M\getLocation when it’s ported to the other library.
	if (($location = getLocation($hEntry)) !== null) {
		$cleansed['location'] = $location;

		// TODO: do additional reverse lookups of address details if none are provided.

		if (!empty($location['latitude']) and !empty($location['longitude'])) {
			// If this is a valid point, add a point with mashed names for elasticsearch to index.
			$cleansed['location_point'] = [
					'lat' => $location['latitude'],
					'lon' => $location['longitude']
			];
		}
	}

	// TODO: figure out what other properties need storing/indexing, and whether anything else needs mashing for
	// elasticsearch to index more easily.

	return [$cleansed, $referencedPosts];
}


/**
 * @param array $resource The Subscriptions Resource array
 * @param boolean $persist default: true Whether or not to actually save parsed results, or just return them.
 */
$app['indexResource'] = $app->protect(function ($resource, $persist=true) use ($app) {
	$loggableResource = is_object($resource) ? clone $resource : $resource;
	$loggableResource['content'] = $loggableResource['mf2'] = 'Truncated for logging';
	$app['logger']->info('Indexing Resource', [
		'resource' => $loggableResource
	]);

	$result = [];
	
	// TODO: Archive the response when taproot/archive allows us to do so without fetching it again.

	/** @var Elasticsearch\Client $es */
	$es = $app['elasticsearch'];

	/** @var \Guzzle\Http\Client $client */
	$client = $app['http.client'];

	$url = $resource['url'];

	// Feed Reader Subscription
	if (!empty($resource['mf2'])) {
		$mf = $resource['mf2'];

		// If there are h-entries on the page, for each of them:
		$hEntries = M\findMicroformatsByType($mf, 'h-entry');
		$hFeeds = M\findMicroformatsByType($mf, 'h-feed');
		if (count($hEntries) == 0 and count($hFeeds) > 0) {
			$hEntries = M\findMicroformatsByType($hFeeds[0]['children'], 'h-entry');
		}

		$result['feed-parse'] = [
			'posts' => [],
			'referenced-posts' => []
		];

		foreach ($hEntries as $hEntry) {
			$cleansed = [];

			// Merge new topic in with any existing topics if we’ve already seen this piece of content
			$existingEntryParams = [
					'index' => 'shrewdness',
					'type' => 'h-entry',
					'id' => M\getProp($hEntry, 'url')
			];
			if ($existingEntryParams['id'] !== null and $es->exists($existingEntryParams)) {
				$existingEntry = $es->get($existingEntryParams);
				$cleansed['topics'] = array_unique(array_merge(@($existingEntry['_source']['topics'] ?: []), [$resource['topic']]));
			} else {
				$cleansed['topics'] = [$resource['topic']];
			}

			list($processedHEntry, $referencedPosts) = processHEntry($hEntry, $mf, $url, true, $client, [$app['purifier'], 'purify']);
			$cleansed = array_merge($cleansed, $processedHEntry);

			$result['feed-parse']['posts'][] = $cleansed;
			$result['feed-parse']['referenced-posts'] = array_merge($result['feed-parse']['referenced-posts'], $referencedPosts);

			// TODO: actually index $cleansed.
			if ($persist) {
				if ($cleansed['url']) {
					$es->index([
						'index' => 'shrewdness',
						'type' => 'h-entry',
						'id' => $cleansed['url'],
						'body' => $cleansed
					]);
				}

				foreach ($referencedPosts as $referencedPost) {
					if ($referencedPost['url']) {
						$es->index([
							'index' => 'shrewdness',
							'type' => 'h-entry',
							'id' => $referencedPost['url'],
							'body' => $referencedPost
						]);
					}
				}
			}
		}
	} else {
		// If there are no h-entries, but the page is still valid HTML, index the page as an ordinary webpage.

		// If the page is not HTML, log as a bad request.
	}

	// Anti-spam measures
	// Find all links not tagged with rel=nofollow.
	
	// For each link, ensure there is a row linking this authority to the links’s authority/origin (find good name here).
	// Where “authority” ≈ domain, with some special cases for silos like Twitter.
	// E.G. authority of http://waterpigs.co.uk/notes/1000 is waterpigs.co.uk
	// authority of https://twitter.com/aaronpk/status/1234567890 is twitter.com/aaronpk
	// Also note relation(s), derived from mf2/rel values, store those as space-separated.

	return $result;
});

return $app;
