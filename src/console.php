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

class LoginableClient extends HttpKernel\Client {
	protected $credentials;

	public function login($details = null) {
		if ($details === null) {
			$this->credentials = ['me' => $this->kernel['owner.url']];
		} elseif (is_string($details)) {
			$this->credentials = ['me' => $details];
		} else {
			$this->credentials = $details;
		}

		return $this->credentials;
	}

	public function logout() {
		$this->credentials = null;
	}

	protected function doRequest($request) {
		if ($this->credentials !== null) {
			$request->attributes->set('indieauth.client.token', $this->credentials);
		}

		return parent::doRequest($request);
	}
}

$console = new Application("Shrewdness", '0.0.1');
$console->getDefinition()->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', 'dev'));

$console->register('shell')
	->setDescription('PHP Shell with $app loaded for quick scripting')
	->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
		$app['exception_handler']->disable();
		$app['debug'] = True;

		Psy\Shell::debug([
			'app' => $app,
			'client' => new LoginableClient($app)
		]);
	});
	
return $console;
