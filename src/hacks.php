<?php

namespace Taproot\Shrewdness;

use BarnabyWalters\Mf2 as M;

// Temporary staging ground for hacky things.

// This is part of Taproot\Text, which will probably be packaged up as an open source library at some point.
function renderTemplate($__basedir, $template, array $__templateData = array()) {
	$render = function ($__path, array $__templateData=array()) use ($__basedir) {
		$render = function ($template, array $data=array()) use ($__basedir) {
			return renderTemplate($__basedir, $template, $data);
		};
		ob_start();
		extract($__templateData);
		unset($__templateData);
		include $__basedir . '/../templates/' . $__path . '.php';
		return ob_get_clean();
	};
	return $render($template, $__templateData);
}


// This really needs to be a representative h-card implementation with purification etc. For the moment, just minimal
// possible code.
function firstHCard(array $mf, $defaultPhoto = null) {
	$hCards = M\findMicroformatsByType($mf, 'h-card');
	if (count($hCards) == 0) {
		return null;
	}

	$h = $hCards[0];
	return [
		'name' => M\getPlaintext($h, 'name'),
		'photo' => M\getPlaintext($h, 'photo', $defaultPhoto),
		'url' => M\getPlaintext($h, 'url', null)
	];
}

function dataPath($path) {
	return __DIR__ . "/../data/{$path}.json";
}

// Loads a JSON file in data/, or returns an empty array.
function loadJson($path) {
	if (file_exists(dataPath($path))) {
		return json_decode(file_get_contents(dataPath($path)), true) ?: [];
	}
	return [];
}

function saveJson($path, $json) {
	return file_put_contents(__DIR__ . "/../data/{$path}.json", json_encode($json, JSON_PRETTY_PRINT));
}


// TODO: put this in mf-cleaner with tests.
// TODO: should all location/adr/geo properties be added to the stack instead of just the first of each?
function getLocation(array $mf) {
	$location = [];

	$locationDataSources = [$mf];
	if (M\hasProp($mf, 'location') and M\isMicroformat($mf['properties']['location'][0])) {
		$locationDataSources[] = $mf['properties']['location'][0];
	}
	if (M\hasProp($mf, 'adr') and M\isMicroformat($mf['properties']['adr'][0])) {
		$locationDataSources[] = $mf['properties']['adr'][0];
	}
	if (M\hasProp($mf, 'geo')) {
		$geo = $mf['properties']['geo'][0];
		if (M\isMicroformat($geo)) {
			$locationDataSources[] = $geo;
		} elseif (is_string($geo)) {
			$parts = parse_url($geo);
			if (!empty($parts['scheme']) and $parts['scheme'] == 'geo' and !empty($parts['path'])) {
				$geoParts = explode(',', $parts['path']);
				$derivedGeo = [
					'type' => ['h-geo'],
					'properties' => [
						'latitude' => [$geoParts[0]],
						'longitude' => [$geoParts[1]]
					]
				];
				if (count($geoParts) > 2) {
					$derivedGeo['properties']['altitude'] = [$geoParts[2]];
				}
				$locationDataSources[] = $derivedGeo;
			}
		}
	}

	$locationProperties = [
		'street-address',
		'extended-address',
		'post-office-box',
		'locality',
		'region',
		'postal-code',
		'country-name',
		'label',
		'latitude',
		'longitude',
		'altitude'
	];

	// Search all the location data sources for each property, storing the first one we come across.
	foreach ($locationProperties as $propName) {
		foreach ($locationDataSources as $mf) {
			if (M\hasProp($mf, $propName)) {
				$location[$propName] = M\getPlaintext($mf, $propName);
			}
		}
	}

	return empty($location) ? null : $location;
}


function firstWith(array $array, array $parameters) {
	foreach ($array as $item) {
		$matches = true;
		foreach ($parameters as $key => $value) {
			if ($item[$key] != $value) {
				$matches = false;
			}
		}
		if ($matches) {
			return $item;
		}
	}

	return null;
}

function replaceFirstWith(array $array, array $parameters, array $replacement) {
	foreach ($array as $i => $item) {
		$matches = true;
		foreach ($parameters as $key => $value) {
			if ($item[$key] != $value) {
				$matches = false;
			}
		}
		if ($matches) {
			$array[$i] = $replacement;
		}
	}

	return $array;
}

function flattenHCard($hCard, $url) {
	$host = parse_url($url, PHP_URL_HOST);
	$author = [
		'name' => $host ?: $url,
		'url' => $host ? "http://{$host}" : null,
		'photo' => false
	];

	$authorProperty = $hCard;
	if(array_key_exists('name', $authorProperty['properties'])) {
		$author['name'] = $authorProperty['properties']['name'][0];
	}

	if(array_key_exists('url', $authorProperty['properties'])) {
		$author['url'] = $authorProperty['properties']['url'][0];
	}

	if(array_key_exists('photo', $authorProperty['properties'])) {
		$author['photo'] = $authorProperty['properties']['photo'][0];
	}

	return $author;
}
