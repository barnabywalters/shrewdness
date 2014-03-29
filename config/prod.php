<?php

use Silex\Provider\MonologServiceProvider;

require __DIR__.'/user.php';

$app->register(new MonologServiceProvider(), array(
	'monolog.logfile' => __DIR__.'/../logs/silex.log',
));
