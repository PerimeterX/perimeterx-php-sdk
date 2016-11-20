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
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \GuzzleHttp\Client|null The Guzzle client.
     */
    public function __construct($config)
    {
        $this->client = new Client(['base_uri' => $config['perimeterx_server_host']]);
        $this->logger = $config['logger'];
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
            $this->logger->error('http error ' . $e->getCode() . ' ' . $e->getMessage());
            return json_encode(['error_msg' => $e->getMessage()]);
        }

        $rawBody = (string)$rawResponse->getBody();
        return $rawBody;
    }

}
