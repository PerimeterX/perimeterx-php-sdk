<?php

namespace PerimeterxTests;

class TestUtils {
    public static function initializeRequest($method = "GET", $uri = "/", $headers = [], $body = null, $streamName = null) {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['QUERY_STRING'] = self::extractQueryFromUri($uri);
        $_SERVER['REMOTE_ADDR'] = '1.1.1.1';
        if ($method === "POST") {
            self::initializePostRequest($streamName, $body);
        }
    }

    private static function extractQueryFromUri($uri) {
        $queryString = '';
        $questionMarkIndex = strpos($uri, "?");
        if ($questionMarkIndex !== false) {
            $queryString = substr($uri, $questionMarkIndex + 1);
        }
        return $queryString;
    }

    private static function initializePostRequest($streamName, $body) {
        if (!empty($body) && !empty($streamName)) {
            $stream = fopen($streamName, 'w');
            fwrite($stream, $body);
            fclose($stream);
        }
    }
}