<?php

namespace Taproot;

use DateTime;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\HttpKernel;

use Psy;

/** @var $app \Silex\Application */

$console = new Application("Shrewdness", '0.0.1');
$console->getDefinition()->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', 'dev'));

$console->register('shell')
	->setDescription('PHP Shell with $app loaded for quick scripting')
	->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
		$app['exception_handler']->disable();
		$app['debug'] = True;

		Psy\Shell::debug([
			'app' => $app,
			'client' => new HttpKernel\Client($app)
		]);
	});
	
return $console;
