<?php

namespace Taproot\Shrewdness;

// Temporary staging ground for hacky things.

function render($template, array $__templateData = array()) {
	$__basedir = __DIR__;
	$render = function ($__path, $__templateData) use ($__basedir) {
		$render = function ($template, $data) use ($__basedir) {
			return render($__basedir, $template, $data);
		};
		ob_start();
		extract($__templateData);
		unset($__templateData);
		include $__basedir . '/../templates/' . $__path . '.php';
		return ob_get_clean();
	};
	return $render($template, $__templateData);
}
