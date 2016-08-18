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
        $this->client = $client ?: new Client(['base_uri' => 'https://sapi.perimeterx.net']);
    }

    /**
     * @inheritdoc
     * @return string
     */
    function sendAsync($json, $token, $local_proxy)
    {
        if ($local_proxy) {
            $host = "localhost";
            $port = 8095;
            $fp = fsockopen("tcp://" . $host, $port, $errno, $errstr, 1);
        } else {
            $host = "sapi.perimeterx.net";
            $port = 443;
            $fp = fsockopen("ssl://" . $host, $port, $errno, $errstr, 1);
        }

        $out = "POST /api/v1/risk/ HTTP/1.1\r\n";
        $out .= "Content-Type: application/json\r\n";
        $out .= "Host: sapi.perimeterx.net\r\n";
        $out .= "Content-Length: " . strlen($json) . "\r\n";
        $out .= "Authorization: Bearer " . $token . "\r\n";
        $out .= "Connection: Close\r\n\r\n";
        $out .= $json;

        fwrite($fp, $out);
        fclose($fp);
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
