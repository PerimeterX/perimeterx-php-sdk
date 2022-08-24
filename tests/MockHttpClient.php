<?php

namespace PerimeterxTests;

use Perimeterx\Utils\IHttpClient;

class MockHttpClient implements IHttpClient {

    private $methodCallbacks;

    /**
     * @var array $methodCallbacks
     */
    public function __construct($methodCallbacks) {
        $this->methodCallbacks = $methodCallbacks;
    }

    public function setBaseUri($baseUri) {
        return;
    }

    public function get($uri, $httpClientOptions) {
        return $this->request('GET', $uri, $httpClientOptions);
    }

    public function post($uri, $httpClientOptions) {
        return $this->request('POST', $uri, $httpClientOptions);
    }

    public function request($method, $uri, $httpClientOptions) {
        $method = strtoupper($method);
        if (array_key_exists($method, $this->methodCallbacks)) {
            return $this->methodCallbacks[$method]($uri, $httpClientOptions);
        }
        return;
    }
}