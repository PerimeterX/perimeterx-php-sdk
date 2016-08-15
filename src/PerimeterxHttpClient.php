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
        $this->client = $client ?: new Client(['base_uri' => 'https://collector.perimeterx.net']);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function sendAsync($url, $method, $json, $headers)
    {
        $params = array('http' => array(
            'method' => $method,
            'content' => $json
        ));
        if ($headers !== null) {
            $params['http']['header'] = $headers;
        }
        $ctx = stream_context_create($params);
        @fopen($url, 'rb', false, $ctx);

        return true;
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function send($url, $method, $json, $headers, $timeout = 0)
    {
        try {
            $rawResponse = $this->client->request($method, $url, ['json' => $json, 'headers' => $headers, 'timeout' => $timeout]);
        } catch (RequestException $e) {
            error_log('http error ' . $e->getCode() . ' ' . $e->getMessage());
            return null;
        }

        $rawBody = (string)$rawResponse->getBody();
        return $rawBody;
    }
}
