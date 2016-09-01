<?php

namespace Perimeterx;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class PerimeterxHttpClient
{
    /**
     * @var \GuzzleHttp\Client The Guzzle client.
     */
    protected $client;

    /**
     * @param \GuzzleHttp\Client|null The Guzzle client.
     */
    public function __construct(Client $client = null)
    {
        $this->client = $client ?: new Client(['base_uri' => 'https://sapi-cdn.perimeterx.net']);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function send($url, $method, $json, $headers, $timeout = 0, $connect_timeout = 0)
    {
        try {
            $rawResponse = $this->client->request($method, $url, 
                [
                'json' => $json, 
                'headers' => $headers, 
                'timeout' => $timeout,
                'connect_timeout' => $connect_timeout
                ]
            );
        } catch (RequestException $e) {
            error_log('http error ' . $e->getCode() . ' ' . $e->getMessage());
            return null;
        }

        $rawBody = (string)$rawResponse->getBody();
        return $rawBody;
    }

}
