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
	return new Guzzle\Http\Client();
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


/**
 * @param array $resource The Subscriptions Resource array
 * @param boolean $persist default: true Whether or not to actually save parsed results, or just return them.
 */
$app['indexResource'] = $app->protect(function ($resource, $persist=true) use ($app) {
	$app['logger']->info('Indexing Resource', [
		'resource' => $resource
	]);

	$result = [];
	
	// TODO: Archive the response when taproot/archive allows us to do so without fetching it again.

	/** @var Elasticsearch\Client $es */
	$es = $app['elasticsearch'];

	$url = $resource['url'];

	// Feed Reader Subscription
	if (!empty($resource['mf2'])) {
		$mf = $resource['mf2'];

		// If there are h-entries on the page, for each of them:
		$hEntries = M\findMicroformatsByType($mf, 'h-entry');

		if (count($hEntries) > 0) {
			$result['feed-parse'] = [
				'posts' => []
			];
		}

		foreach ($hEntries as $hEntry) {
			// Use comment-presentation algorithm to clean up.
			$cleansed = comments\parse($hEntry);

			// Merge new topic in with any existing topics if we’ve already seen this piece of content
			$existingEntryParams = [
					'index' => 'shrewdness',
					'type' => 'h-entry',
					'id' => $cleansed['url']
			];
			if ($es->exists($existingEntryParams)) {
				$existingEntry = $es->get($existingEntryParams);
				$cleansed['topics'] = array_unique(array_merge(@($existingEntry['_source']['topics'] ?: []), [$resource['topic']]));
			} else {
				$cleansed['topics'] = [$resource['topic']];
			}

			$indexedContent = M\getPlaintext($hEntry, 'content', $cleansed['text']);

			$displayContent = $app['purifier']->purify(M\getHtml($hEntry, 'content'));

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
				$cleansed['photo'] = $app['purifier']->purify(M\getHtml($hEntry, 'photo'));
			}

			// TODO: these are going to need some cleaning. If they’re strings, fetching; microformats cleaning, authorship etc.
			// We also need to look in rel=in-reply-to. like, repost not used as commonly so are lower priority.
			if (M\hasProp($hEntry, 'in-reply-to')) {
				$cleansedReplyContexts = [];
				foreach ($hEntry['properties']['in-reply-to'] as $inReplyTo) {
					if (is_string($inReplyTo)) {
						// It’s (hopefully) a URL, fetch it and look for a h-entry.
						$irtMf = Mf2\fetch($inReplyTo);
						$irtHEntries = M\findMicroformatsByType($irtMf, 'h-entry');
						if (count($irtHEntries) == 0) {
							// This page doesn’t have any microformats, so create a dummy one with just the URL.
							$irtHCite = [
								'type' => ['h-cite', 'h-x-dummy-cite'],
								'properties' => [
									'url' => [$inReplyTo],
									'name' => [$inReplyTo]
								]
							];
						} else {
							$irtHCite = $irtHEntries[0];
						}
					} elseif (M\isMicroformat($inReplyTo)) {
						// They’ve provided an h-cite! How considerate.
						$irtHCite = $inReplyTo;
					} else {
						// uh
						continue;
					}

					// Perform normalisations to the derived $irtHEntry, regardless of source.
					// comments\parse only works with h-entries, so add 'h-entry' to the context h-cite
					$irtHCite['type'][] = 'h-entry';
					$irtCleansed = comments\parse($irtHCite);
					// For the moment that will do for reply contexts. In the future, more work should be done:
					// Authorship, datetime checking, parsing of recursive in-reply-to properties

					$cleansedReplyContexts[] = $irtCleansed;
				}

				$cleansed['in-reply-to'] = $cleansedReplyContexts;
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
						$response = $app['http.client']->head("{$cleansed['author']['url']}/favicon.ico")->send();
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

			$result['feed-parse']['posts'][] = $cleansed;

			// TODO: actually index $cleansed.
			if ($persist) {
				$es->index([
					'index' => 'shrewdness',
					'type' => 'h-entry',
					'id' => $cleansed['url'],
					'body' => $cleansed
				]);
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
