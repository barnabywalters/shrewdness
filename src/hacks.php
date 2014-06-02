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
