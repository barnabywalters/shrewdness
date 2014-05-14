<?php

ini_set('display_errors', 0);

ob_start();
require_once __DIR__.'/../vendor/autoload.php';
ob_end_clean();

/** @var $app \Silex\Application */

$app = require __DIR__.'/../src/app.php';
require __DIR__.'/../config/prod.php';
require __DIR__.'/../src/controllers.php';
$app->run();
