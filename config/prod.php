<?php

use Silex\Provider\MonologServiceProvider;

$app['indieauth.url'] = 'https://indieauth.com/';
$app['rememberme.cookiename'] = 'shrewdnessauth';

require __DIR__.'/user.php';

$app->register(new MonologServiceProvider(), array(
	'monolog.logfile' => __DIR__.'/../logs/silex.log',
));
