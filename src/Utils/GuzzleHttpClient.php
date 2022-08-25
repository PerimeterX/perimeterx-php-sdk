<?php

namespace Perimeterx\Utils;

use Perimeterx\Utils\IHttpClient;
use GuzzleHttp\Client;

class GuzzleHttpClient implements IHttpClient {
    private $guzzleClient;

    public function __construct($baseUri = null, $guzzleHandler = null) {
        if (!empty($baseUri)) {
            $initParams = ['base_uri' => $baseUri];
            if (!empty($guzzleHandler)) {
                $initParams['handler'] = $guzzleHandler;
            }
            $this->guzzleClient = new Client($initParams);
        }
    }

    public function setBaseUri($baseUri) {
        if (!empty($baseUri)) {
            $this->guzzleClient = new Client(['base_uri' => $baseUri]);
        }
    }

    public function get($uri, $httpClientOptions) {
        return $this->request('GET', $uri, $httpClientOptions);
    }

    public function post($uri, $httpClientOptions) {
        return $this->request('POST', $uri, $httpClientOptions);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $httpClientOptions
     */
    public function request($method, $url, $httpClientOptions) {
        if (!isset($this->guzzleClient)) {
            return null;
        }

        $options = [];
        foreach (['headers', 'form', 'json', 'query', 'body'] as $key) {
            if (array_key_exists($key, $httpClientOptions)) {
                $options[$key] = $httpClientOptions[$key];
            }
        }

        return $this->guzzleClient->request($method, $url, $options);
    }
}