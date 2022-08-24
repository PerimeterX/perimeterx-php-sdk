<?php

namespace Perimeterx\Utils;

/**
 * $httpClientOptions are the same as GuzzleHttp\RequestOptions, specifically using values
 * -> 'headers'
 * -> 'query'
 * -> 'json'
 * -> 'form'
 */

interface IHttpClient {
    public function setBaseUri($baseUri);
    public function request($method, $uri, $httpClientOptions);
    public function get($uri, $httpClientOptions);
    public function post($uri, $httpClientOptions);
}