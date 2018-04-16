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

        $json = self::fixJsonBody($json);
        $rawResponse = $this->client->request($method, $url,
            [
            'json' => $json,
            'headers' => $headers,
            'timeout' => $timeout,
            'connect_timeout' => $connect_timeout
            ]
        );

        $rawBody = (string)$rawResponse->getBody();
        return $rawBody;
    }

    /**
     * This function static so custom clients can call it (when using custom_risk_handler)
     * @param $jsonArray
     * @return array
     */
    public static function fixJsonBody($jsonArray)
    {
        // ensure incoming array is UTF-8 encoded to avoid JSON_ERROR_UTF8 errors
        array_walk_recursive(
            $jsonArray,
            function(&$value, $key) // assume key is ok, no need to fix it
            {
                if (is_string($value)) {
                    $value = utf8_encode($value);
                } elseif ($value instanceof \stdClass) {
                    $value = self::fixJsonBody(get_object_vars($value));
                }
            }
        );
        return $jsonArray;
    }

}
