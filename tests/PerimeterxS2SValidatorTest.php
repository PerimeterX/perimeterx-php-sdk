<?php

use Perimeterx\Perimeterx;
use Perimeterx\PerimeterxContext;
use Perimeterx\PerimeterxS2SValidator;
use Perimeterx\PerimeterxHttpClient;
use Psr\Log\AbstractLogger;

class PerimeterxS2SValidatorTest extends PHPUnit_Framework_TestCase
{
    // randomly generated fake values
    const COOKIE_KEY = '549Z5UsasvfmVS6kAR3r4ydPnQdnnW4Gcwk35hj5tatZ5B2dqjrQvMMyLAJN5de3';
    const COOKIE_UUID = '9e70ed8b-c205-4a9d-bf3a-e92a945be600';
    const COOKIE_VID = '69521dce-ab65-11e6-80f5-76304dec7eb7';
    const AUTH_TOKEN = 'another auth token';
    const IP = '10.10.10.10';
    const USER_AGENT = 'Mozilla';
    const URI = "/";
    const URL = "http://localhost:3000";
    const S2S_CALL_REASON = "cookie_decryption_failed";
    const MODULE_VERSION = 'Test v1.0';
    const HTTP_METHOD = 'GET';
    const HTTP_VERSION = '1.1';
    const MODULE_MODE = 1;
    const PX_COOKIE_HMAC = '9e70ed8b';
    const API_TIMEOUT = 0 ;
    const API_CONNECT_TIMEOUT = 0;
    const BLOCKING_SCORE = 70;

    public function testAttachPxOrigCookie() {
        $pxCookie = 'this is a fake cookie';

        $http_client = $this->createMock(PerimeterxHttpClient::class);
        $http_client->expects($spy = $this->any())
            ->method('send');

        $pxCtx = $this->getPxContext($pxCookie);
        $pxConfig = $this->getPxConfig($this->getMockLogger('info', 'attaching px_orig_cookie to request'), $http_client);

        $validator = new PerimeterxS2SValidator($pxCtx, $pxConfig);
        $validator->verify();

        $invocations = $spy->getInvocations();

        $last = end($invocations);
        $this->assertEquals($pxCookie, $last->parameters[2]["additional"]["px_cookie_orig"]);
    }

    public function testRiskResponseUnencodedPxhdCookie() {
        $pxhdCookie = "\this cookie has bad \values like \013 and \014 that p\roper cookies should\n't have";

        $http_client = $this->createMock(PerimeterxHttpClient::class);
        $http_client->method('send')
            ->willReturn(json_encode([
                "status" => 0,
                "score" => 0,
                "action" => 'c',
                "uuid" => "uuid",
                "pxhd" => $pxhdCookie
            ]));

        $pxCtx = $this->getPxContext();
        $pxConfig = $this->getPxConfig($this->getMockLogger(), $http_client);

        $validator = new PerimeterxS2SValidator($pxCtx, $pxConfig);
        // asserts that it does not throw an exception/warning
        $this->assertNull($validator->verify());
    }

    public function testS2SErrorHttpClientThrowsException() {
        $exception_message = "Exception message!";
        $http_client = $this->createMock(PerimeterxHttpClient::class);
        $http_client->expects($this->once())
            ->method('send')
            ->willThrowException(new Exception($exception_message));

        $pxCtx = $this->getPxContext();
        $pxConfig = $this->getPxConfig($this->getMockLogger(), $http_client);

        $validator = new PerimeterxS2SValidator($pxCtx, $pxConfig);
        $validator->verify();

        $this->assertEquals("s2s_error", $pxCtx->getPassReason());
        $this->assertEquals("unknown_error", $pxCtx->getS2SErrorReason());
        $this->assertContains($exception_message, $pxCtx->getS2SErrorMessage());
    }

    /**
     * @dataProvider provideS2SErrorData
     */

    public function testS2SError($httpClientReturnValue, $s2sErrorReason, $s2sErrorMessage, $httpStatus, $httpMessage) {
        $pxCtx = $this->getPxContext();
        $http_client = $this->createMock(PerimeterxHttpClient::class);
        $http_client->expects($this->once())
            ->method('send')
            ->willReturn($this->assignS2SHttpInfoAndReturn($pxCtx, $httpStatus, $httpMessage, $httpClientReturnValue));

        $pxConfig = $this->getPxConfig($this->getMockLogger(), $http_client);

        $validator = new PerimeterxS2SValidator($pxCtx, $pxConfig);
        $validator->verify();

        $this->assertEquals("s2s_error", $pxCtx->getPassReason());
        $this->assertEquals($s2sErrorReason, $pxCtx->getS2SErrorReason());
        if (!empty($s2sErrorMessage)) {
            $this->assertContains($s2sErrorMessage, $pxCtx->getS2SErrorMessage());
        }
        $this->assertEquals($httpStatus, $pxCtx->getS2SErrorHttpStatus());
        $this->assertEquals($httpMessage, $pxCtx->getS2SErrorHttpMessage());
    }

    public function provideS2SErrorData() {
        $errorMessage = "Error message!!!";
        $errorCode = 7;
        return array(
            array(json_encode(['error_msg' => $errorMessage, 'error_code' => $errorCode]), "unknown_error", $errorMessage, null, null),
            array(json_encode(['error_msg' => $errorMessage, 'error_code' => $errorCode]), "unknown_error", $errorMessage, 607, "Who Knows!"),
            array(json_encode([]), "unknown_error", "", 399, "Some Other Error"),
            array(json_encode([]), "bad_request", "", 400, "Bad Request"),
            array(json_encode([]), "bad_request", "", 499, "Some Other Error"),
            array(json_encode([]), "server_error", "", 500, "Internal Server Error"),
            array(json_encode([]), "server_error", "", 501, "Some Other Error"),
            array(json_encode([]), "server_error", "", 599, "Some Other Error"),
            array(json_encode([]), "unknown_error", "", 600, "Some Other Error"),
            array(json_encode(['status' => 0, 'action' => $errorMessage]), "invalid_response", $errorMessage, 200, 'OK'),
            array(json_encode(['status' => -1, 'message' => $errorMessage]), "request_failed_on_server", $errorMessage, 200, 'OK'),
            array(json_encode(['random_key' => 'random_value']), "unknown_error", '"random_key":"random_value"', null, null)
        );
    }

    private function assignS2SHttpInfoAndReturn($pxCtx, $httpStatus, $httpMessage, $returnValue = null) {
        if (isset($httpStatus)) {
            $pxCtx->setS2SErrorHttpStatus($httpStatus);
        }
        if (isset($httpMessage)) {
            $pxCtx->setS2SErrorHttpMessage($httpMessage);
        }
        if (isset($returnValue)) {
            return $returnValue;
        }
        return json_encode(['error_msg' => $httpMessage, 'error_code' => $httpStatus]);
    }

    private function getPxConfig($logger, 
                                 $httpClient,
                                 $cookie_key = self::COOKIE_KEY,
                                 $blocking_score = self::BLOCKING_SCORE,
                                 $auth_token = self::AUTH_TOKEN,
                                 $sdk_name = self::MODULE_VERSION,
                                 $module_mode = self::MODULE_MODE,
                                 $api_timeout = self::API_TIMEOUT,
                                 $api_connection_timeout = self::API_CONNECT_TIMEOUT)
    {
        return [
            'cookie_key' => $cookie_key,
            'blocking_score' => $blocking_score,
            'logger' => $logger,
            'http_client' => $httpClient,
            'auth_token' => $auth_token,
            'sdk_name' => $sdk_name,
            'module_mode' => $module_mode,
            'api_timeout' => $api_timeout,
            'api_connect_timeout' => $api_connection_timeout
        ];
    }

    /**
     * @return PerimeterxContext
     */
    private function getPxContext($pxCookie = "", 
                                  $userAgent = self::USER_AGENT, 
                                  $ip = self::IP, 
                                  $uri = self::URI,
                                  $url = self::URI, 
                                  $s2s_call_reason = self::S2S_CALL_REASON, 
                                  $http_version = self::HTTP_VERSION,
                                  $http_method = self::HTTP_METHOD, 
                                  $cookie_hmac = self::PX_COOKIE_HMAC)
{
        $pxCtx = $this->getMockBuilder(PerimeterxContext::class)
            ->disableOriginalConstructor()
            ->setMethods([
              'getPxCookie',
              'getUserAgent',
              'getIp',
              'getUri',
              'getUrl',
              'getS2SCallReason',
              'getHttpMethod',
              'getHttpVersion',
              'getCookieHmac',
              'getHeaders'
            ])
            ->getMock();
        $pxCtx->expects($this->any())
            ->method('getPxCookie')
            ->willReturn($pxCookie);
        $pxCtx->expects($this->any())
            ->method('getUserAgent')
            ->willReturn($userAgent);
        $pxCtx->expects($this->any())
            ->method('getIp')
            ->willReturn($ip);
        $pxCtx->expects($this->any())
            ->method('getUri')
            ->willReturn($uri);
        $pxCtx->expects($this->any())
            ->method('getUrl')
            ->willReturn($url);
        $pxCtx->expects($this->any())
            ->method('getS2SCallReason')
            ->willReturn($s2s_call_reason);
        $pxCtx->expects($this->any())
            ->method('getHttpMethod')
            ->willReturn($http_method);
        $pxCtx->expects($this->any())
            ->method('getHttpVersion')
            ->willReturn($http_version);
        $pxCtx->expects($this->any())
            ->method('getCookieHmac')
            ->willReturn($cookie_hmac);
        $pxCtx->expects($this->any())
            ->method('getHeaders')
            ->willReturn([]);

        return $pxCtx;
    }

    private function getMockLogger($expected_level = null, $expected_message = null)
    {
        $levels = ['info', 'warning', 'error'];
        $logger = $this->createMock(AbstractLogger::class);

        foreach ($levels as $level) {
            if ($expected_level === $level) {
                $logger->expects($this->once())
                    ->method($expected_level)
                    ->with($expected_message);
            }
        }
        return $logger;
    }
  }
?>
