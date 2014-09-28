<?php

namespace Taproot;

use DateTime;
use Exception;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\HttpKernel;
use Symfony\Component\Routing;

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


$console->register('resubscribe')
	->setDescription('Resubscribe to all subscriptions')
	->addOption('unsubscribe', 'u', InputArgument::OPTIONAL, 'Unsubscribe from each feed before re-subscribing', False)
	->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
			/** @var Subscriptions\SubscriptionStorage $ss */
			$ss = $app['subscriptions.storage'];
			/** @var Subscriptions\PushHub $dh */
			$dh = $app['subscriptions.defaulthub'];

			$rq = new Routing\RequestContext(null, null, $app['host'], 'https');
			$app['url_generator']->setContext($rq);

			foreach ($ss->getSubscriptions() as $subscription) {
				if ($input->getOption('unsubscribe')) {
					$output->writeln("Unsubscribing from {$subscription['topic']}");

					if ($subscription['hub'] == $dh->getUrl()) {
						$unsubhub = $dh;
					} else {
						$unsubhub = new Subscriptions\PushHub($subscription['hub']);
					}

					$output->writeln(" -> Unsubscribing from hub: {$unsubhub}");

					$callback = $app['url_generator']->generate('subscriptions.id.ping', ['id' => $subscription['id']], true);

					$result = $unsubhub->unsubscribe($subscription['topic'], $callback);
					if ($result instanceof Exception) {
						$output->writeln(" -> ERROR: {$result->getMessage()}");
					}
				}

				$output->writeln("Re-subscribing to {$subscription['topic']} (ID {$subscription['id']})");
				try {
					list($newSub, $err) = Subscriptions\subscribe($app, $subscription['topic']);
				} catch (Exception $err) {
				}
				if ($err) {
					$output->writeln(" -> ERROR: {$err->getMessage()}");
				}
				$output->writeln('');
			}
		});


$console->register('prune')
	->setDescription('Unsubscribe from any feeds which are no longer in anyone’s columns')
	->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
			/** @var Subscriptions\PushHub $dh */
			$dh = $app['subscriptions.defaulthub'];
			$rq = new Routing\RequestContext(null, null, $app['host'], 'https');
			$app['url_generator']->setContext($rq);

			$allTopics = [];
			foreach (glob(__DIR__ . '/../data/*') as $userPath) {
				$config = json_decode(file_get_contents("{$userPath}/columns.json"), true);
				foreach ($config['columns'] as $column) {
					if (!empty($column['sources']) and is_array($column['sources'])) {
						$allTopics = array_unique(array_merge($allTopics, array_map(function ($source) {
							return $source['topic'];
						}, $column['sources'])));
					}
				}
			}

			$output->writeln("All topics in use:");
			foreach ($allTopics as $topic) {
				$output->writeln(" -> {$topic}");
			}

			$subscriptions = $app['subscriptions.storage']->getSubscriptions();

			foreach ($subscriptions as $subscription) {
				if (!in_array($subscription['topic'], $allTopics)) {
					if ($subscription['hub'] == $dh->getUrl()) {
						$unsubhub = $dh;
					} else {
						$unsubhub = new Subscriptions\PushHub($subscription['hub']);
					}
					$callback = $app['url_generator']->generate('subscriptions.id.ping', ['id' => $subscription['id']], true);
					$unsubhub->unsubscribe($subscription['topic'], $callback);
					$output->writeln("Unsubscribed from {$subscription['topic']} at {$unsubhub}");
				}
			}
		});

	$console->register('poll')
		->setDescription('Polls any feeds subscribed to with a URL of inert-hub')
		->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
				/** @var Subscriptions\SubscriptionStorage */
				$storage = $app['subscriptions.storage'];
				/** @var Subscriptions\PushHub $defaultHub */
				$defaultHub = $app['subscriptions.defaulthub'];

				foreach ($storage->getSubscriptionsForHub($defaultHub->getUrl()) as $subscription) {
					$output->writeln("Fetching {$subscription['topic']}");
					list($context, $err) = Subscriptions\manualFetch($app, $subscription['topic'], $app['http.client']);
					if ($err === null) {
						$output->writeln(" -> Successfully fetched.");
					} else {
						$output->writeln(" -> <error>Error:</error> {$err->getMessage()}");
					}
				}
			});


return $console;
