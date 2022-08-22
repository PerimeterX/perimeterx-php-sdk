<?php

use GuzzleHttp\Psr7\Response;
use Perimeterx\PerimeterxFirstPartyClient;
use Perimeterx\PerimeterxLogger;

use PerimeterxTests\MockHttpClient;
use PerimeterxTests\TestUtils;

class PerimeterxFirstPartyClientTest extends PHPUnit_Framework_TestCase {
    private function getTestPxConfig() {
        $pxConfig = [
            'app_id' => 'PX_APP_ID',
            'cookie_key' => 'PX_COOKIE_KEY',
            'auth_token' => 'PX_AUTH_TOKEN',
            'sensitive_headers' => [],
            'debug_mode' => false
        ];
        $pxConfig['logger'] = new PerimeterxLogger($pxConfig);
        return $pxConfig;
    }

    public function testNonFirstPartyPath() {
        TestUtils::initializeRequest('GET', '/not/a/first_party/path');
        $firstPartyClient = new PerimeterxFirstPartyClient($this->getTestPxConfig(), new MockHttpClient([]));
        $this->assertNull($firstPartyClient->handleFirstParty());
    }

    /**
     * @dataProvider provideFirstPartyPaths
     */
    public function testFirstPartyPathsSendProperRequest($path, $pathToCollector, $domain) {
        TestUtils::initializeRequest('GET', $path);

        $actualUri = '';
        $actualHeaders = [];

        $mockHttpClientCallbacks = ['GET' => function($uri, $httpOptions) use (&$actualUri, &$actualHeaders) {
            $actualUri = $uri;
            $actualHeaders = $httpOptions['headers'];
            return new Response();
        }];
        $firstPartyClient = new PerimeterxFirstPartyClient($this->getTestPxConfig(), new MockHttpClient($mockHttpClientCallbacks));
        $firstPartyClient->handleFirstParty();

        $this->assertEquals($pathToCollector, $actualUri);
        $this->assertEquals($domain, $actualHeaders['Host']);
        $this->assertEquals('1', $actualHeaders['X-PX-First-Party']);
        $this->assertEquals($_SERVER['REMOTE_ADDR'], $actualHeaders['X-PX-Enforcer-True-IP']);
    }

    /**
     * @dataProvider provideFirstPartyPathsAndErrorResponseCodes
     */
    public function testFirstPartyPathsReturn200OnHttpStatusError($path, $responseStatus) {
        TestUtils::initializeRequest('GET', $path);
        $mockHttpClientCallbacks = ['GET' => function() use ($responseStatus) { return new Response($responseStatus, [], "Response body with error"); }];
        $firstPartyClient = new PerimeterxFirstPartyClient($this->getTestPxConfig(), new MockHttpClient($mockHttpClientCallbacks));
        $response = $firstPartyClient->handleFirstParty();
        $this->assertEquals(200, http_response_code());
        $this->assertEquals('', $response);
    }

    /**
     * @dataProvider provideFirstPartyPaths
     */
    public function testFirstPartyPathsReturn200OnException($firstPartyPath) {
        TestUtils::initializeRequest('GET', $firstPartyPath);
        $mockHttpClientCallbacks = ['GET' => function() { throw new Exception("Random Exception"); }];
        $firstPartyClient = new PerimeterxFirstPartyClient($this->getTestPxConfig(), new MockHttpClient($mockHttpClientCallbacks));
        $response = $firstPartyClient->handleFirstParty();
        $this->assertEquals(200, http_response_code());
        $this->assertEquals('', $response);
    }

    public function provideFirstPartyPathsAndErrorResponseCodes() {
        $retval = [];
        foreach ($this->provideFirstPartyPaths() as $pathAndDomain) {
            foreach ([400, 404, 500] as $status) {
                array_push($retval, [$pathAndDomain[0], $status]);
            }
        }

        return $retval;
    }

    public function provideFirstPartyPaths() {
        return [
            ['/_APP_ID/init.js', '/PX_APP_ID/main.min.js', 'client.perimeterx.net'],
            ['/_APP_ID/captcha.js', '/PX_APP_ID/captcha.js', 'captcha.px-cdn.net'],
            ['/_APP_ID/xhr/api/v2/collector', '/api/v2/collector', 'collector.perimeterx.net']
        ];
    }
}