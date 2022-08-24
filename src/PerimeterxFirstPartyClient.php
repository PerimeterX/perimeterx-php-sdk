<?php

namespace Perimeterx;

final class PerimeterxFirstPartyClient {
    const CLIENT_DOMAIN = 'client.perimeterx.net';
    const CAPTCHA_DOMAIN = 'captcha.px-cdn.net';
    const XHR_DOMAIN = 'collector-{{app_id}}.perimeterx.net';
    const FIRST_PARTY_HEADER_NAME = 'X-PX-First-Party';
    const FIRST_PARTY_HEADER_VALUE = '1';

    const EMPTY_GIF_B64 = 'R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';

    private $pxConfig;
    private $logger;
    private $httpClient;

    /**
     * @param array $pxConfig
     * @param Perimeterx\Utils\IHttpClient $httpClient
     */

    public function __construct($pxConfig, $httpClient) {
        $this->pxConfig = $pxConfig;
        $this->logger = $pxConfig['logger'];
        $this->httpClient = $httpClient;
    }

    public function handleFirstParty() {
        $appIdSubstr = substr($this->pxConfig['app_id'], 2);
        $initJsPath = "/$appIdSubstr/init.js";
        $xhrPath = "/$appIdSubstr/xhr";
        $captchaJsPath = "/$appIdSubstr/captcha";

        $uri = strtok($_SERVER['REQUEST_URI'], "?");
        $method = $_SERVER['REQUEST_METHOD'];

        $retval = null;

        if ($uri === $initJsPath) {
            $retval = $this->handleFirstPartyClient();
        } else if (strpos($uri, $xhrPath) !== false) {
            $retval = $this->handleFirstPartyXHR($xhrPath, $method, $uri);
        } else if (strpos($uri, $captchaJsPath) !== false) {
            $retval = $this->handleFirstPartyCaptcha($uri, $captchaJsPath);
        } else {
            $this->logger->debug("$method $uri did not match any first party path");
        }
        return $retval;
    }

    private function handleFirstPartyClient() {
        $this->logger->debug('first party client script detected');
        return $this->handleFirstPartyScript(self::CLIENT_DOMAIN, "/{$this->pxConfig['app_id']}/main.min.js");
    }

    private function handleFirstPartyCaptcha($uri, $captchaJsPath) {
        $this->logger->debug('first party captcha script detected');
        $uri = str_replace($captchaJsPath, "", $uri);
        return $this->handleFirstPartyScript(self::CAPTCHA_DOMAIN, $uri);
    }

    private function handleFirstPartyXHR($xhrPath, $method, $uri) {
        $this->logger->debug('first party xhr detected');

        $thirdPartyUri = str_replace($xhrPath, '', $uri);
        $isGifRequest = strpos($thirdPartyUri, ".gif") !== false;
        $contentType = $isGifRequest ? "image/gif" : "application/json";

        try {
            $domain = str_replace("{{app_id}}", strtolower($this->pxConfig['app_id']), self::XHR_DOMAIN);
            $body = $method === 'GET' ? null : PerimeterxUtils::getPostRequestBody();
            $query = $method === 'GET' ? $_SERVER['QUERY_STRING'] : '';
            $headers = $this->prepareXhrHeaders();
            $rawResponse = $this->makeFirstPartyRequest($domain, $method, $thirdPartyUri, $headers, $query, $body);
            return $this->returnFirstPartyResponseBasedOn($rawResponse, $contentType);
        } catch (\Exception $e) {
            $this->logger->debug("Error handling request to $uri - {$e->getMessage()}");
            $defaultResponseBody = $isGifRequest ? self::EMPTY_GIF_B64 : '';
            return $this->returnFirstPartyResponse($contentType, $defaultResponseBody, 200);
        }
    }

    private function prepareXhrHeaders() {
        $headers = PerimeterxUtils::filterSensitiveHeaders(PerimeterxUtils::getAllHeaders(), $this->pxConfig['sensitive_headers']);
        $pxvid = PerimeterxUtils::getCookieValue('_pxvid');
        if (!empty($pxvid)) {
            if (isset($headers['Cookie'])) {
                $headers['Cookie'] += "; _pxvid=$pxvid";
            } else {
                $headers['Cookie'] = "_pxvid=$pxvid";
            }
        }
        return $headers;
    }

    private function handleFirstPartyScript($domain, $uri) {
        $contentType = "application/javascript";
        try {
            $rawResponse = $this->makeFirstPartyRequest($domain, $_SERVER['REQUEST_METHOD'], $uri, [], $_SERVER['QUERY_STRING']);
            return $this->returnFirstPartyResponseBasedOn($rawResponse, $contentType);
        } catch (\Exception $e) {
            $this->logger->debug("Error handling request to $uri - {$e->getMessage()}");
            return $this->returnFirstPartyResponse($contentType, '', 200);
        }
    }

    private function makeFirstPartyRequest($domain, $method, $uri, $headers = [], $query = '', $body = '') {
        $headers = array_merge($headers, [
            'Host' => $domain,
            self::FIRST_PARTY_HEADER_NAME => self::FIRST_PARTY_HEADER_VALUE,
            'X-PX-Enforcer-True-IP' => $_SERVER['REMOTE_ADDR']
        ]);

        $options = [
            'headers' => $headers,
        ];

        if (!empty($query)) {
            $options = array_merge($options, ['query' => $query]);
        }

        if ($method === 'POST' && !empty($body)) {
            $options['body'] = $body;
        }

        $this->httpClient->setBaseUri("https://$domain");
        return $this->httpClient->request($method, $uri, $options);
    }

    private function returnFirstPartyResponseBasedOn($rawResponse, $contentType) {
        $statusCode = $rawResponse->getStatusCode();
        $responseContentType = $rawResponse->getHeader('content-type');
        if (!empty($responseContentType)) {
            $contentType = $responseContentType[0];
        }
        if ($statusCode >= 400) {
            return $this->returnFirstPartyResponse($contentType, '', 200);
        } else {
            $resBody = (string)$rawResponse->getBody();
            return $this->returnFirstPartyResponse($contentType, $resBody, $statusCode);
        }
    }

    private function returnFirstPartyResponse($contentType, $responseBody, $statusCode) {
        http_response_code($statusCode);
        header("Content-Type: $contentType");
        return $responseBody;
    }
}