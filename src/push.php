<?php

namespace Taproot;

use Guzzle;
use Mf2;

function pushLinksForResponse(Guzzle\Http\Message\Response $resp) {
	$self = null;
	$hubs = [];
	
	$linkHeader = $resp->getHeader('link');
	if ($linkHeader instanceof Guzzle\Http\Message\Header\Link) {
		$links = $linkHeader->getLinks();
		foreach ($links as $link) {
			if (strpos(" {$link['rel']} ", ' self ') !== false) {
				$self = $link['url'];
			}
			if (strpos(" {$link['rel']} ", ' hub ') !== false) {
				$hubs[] = $link['url'];
			}
		}
	}
	
	if (strpos($resp->getContentType(), 'html') !== false) {
		$mf = Mf2\parse($resp->getBody(true), $resp->getEffectiveUrl());
		if (!empty($mf['rels']['hub'])) {
			$hubs = array_merge($hubs, $mf['rels']['hub']);
		}
		
		if (!empty($mf['rels']['self']) and $self === null) {
			$self = $mf['rels']['self'][0];
		}
	}
	
	return [
		'self' => $self,
		'hub' => $hubs
	];
}

// TODO: add secret handling.
class PushHub {
	protected $url;
	protected $client;
	
	public function __construct($url, $client = null) {
		$this->url = $url;
		if ($client === null) {
			$client = new Guzzle\Http\Client();
		}
		$this->client = $client;
	}
	
	public function getUrl() {
		return $this->url;
	}
	
	public function subscribe($url, $callback) {
		try {
			$response = $this->client->post($this->url)->addPostFields([
				'hub.mode' => 'subscribe',
				'hub.topic' => $url,
				'hub.callback' => $callback
			])->send();
			return true;
		} catch (Guzzle\Common\Exception\GuzzleException $e) {
			return $e;
		}
	}
	
	public function unsubscribe($url, $callback) {
		try {
			$response = $this->client->post($this->url)->addPostFields([
				'hub.mode' => 'unsubscribe',
				'hub.topic' => $url,
				'hub.callback' => $callback
			])->send();
			return true;
		} catch (Guzzle\Common\Exception\GuzzleException $e) {
			return $e;
		}
	}
	
	public function __toString() {
		return "Hub @ {$this->url}";
	}
}

class SuperfeedrHub extends PushHub {
	protected $username;
	protected $token;
	
	public function __construct($username, $token, $client = null) {
		parent::__construct('https://push.superfeedr.com');
		$this->username = $username;
		$this->token = $token;
		
		if ($client === null) {
			$client = new Guzzle\Http\Client();
		}
		$client->getConfig()->setPath('request.options/auth', [$username, $token]);
		$this->client = $client;
	}
}
