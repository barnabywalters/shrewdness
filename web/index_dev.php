<?php

use Symfony\Component\ClassLoader\DebugClassLoader;
use Symfony\Component\HttpKernel\Debug\ErrorHandler;
use Symfony\Component\HttpKernel\Debug\ExceptionHandler;

if (PHP_SAPI === 'cli-server') {
	$path = __DIR__ . $_SERVER['REQUEST_URI'];
	$isFile = file_exists($path) && !is_dir($path);
	$isViewableFolder = file_exists(rtrim($path, '/').'/index.html');
	if ($isFile or $isViewableFolder) {
		return false;
	}
}

ob_start();
require_once __DIR__.'/../vendor/autoload.php';
ob_end_clean();

date_default_timezone_set('UTC');

error_reporting(-1);
DebugClassLoader::enable();
ErrorHandler::register();
if ('cli' !== php_sapi_name()) {
    ExceptionHandler::register();
}

/** @var $app \Silex\Application */

$app = require __DIR__.'/../src/app.php';
require __DIR__.'/../config/dev.php';
require __DIR__.'/../src/controllers.php';
$app->run();
