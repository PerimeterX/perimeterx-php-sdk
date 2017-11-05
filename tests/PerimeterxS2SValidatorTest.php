<?php

use Perimeterx\PerimeterxContext;
use Perimeterx\PerimeterxS2SValidator;
use Perimeterx\PerimeterxHttpClient;
use PHPUnit\Framework\TestCase;
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

  public function testAttachPxOrigCookie() {
      $pxCookie = 'this is a fake cookie';
      $userAgent = self::USER_AGENT;
      $ip = self::IP;
      $uri = self::URI;
      $url = self::URL;
      $s2s_call_reason = self::S2S_CALL_REASON;
      $http_version = self::HTTP_VERSION;
      $http_method = self::HTTP_METHOD;
      $cookie_hmac = self::PX_COOKIE_HMAC;

      $http_client = $this->createMock(PerimeterxHttpClient::class);
      $http_client->expects($spy = $this->any())
        ->method('send');

      $pxCtx = $this->getPxContext(
        $pxCookie, $userAgent, $ip, $uri,
        $url, $s2s_call_reason, $http_version,
        $http_method, $cookie_hmac
      );

      $pxConfig = [
          'cookie_key' => self::COOKIE_KEY,
          'blocking_score' => 70,
          'logger' => $this->getMockLogger('info', 'attaching px_orig_cookie to request'),
          'http_client' => $http_client,
          'auth_token' => self::AUTH_TOKEN,
          'sdk_name' => self::MODULE_VERSION,
          'module_mode' => self::MODULE_MODE,
          'api_timeout' => self::API_TIMEOUT,
          'api_connect_timeout' => self::API_CONNECT_TIMEOUT
      ];

      $validator = new PerimeterxS2SValidator($pxCtx, $pxConfig);
      $validator->verify();

      $invocations = $spy->getInvocations();

      $last = end($invocations);
      $this->assertEquals($pxCookie, $last->parameters[2]["additional"]["px_cookie_orig"]);
    }


    private function getPxContext(
      $pxCookie, $userAgent, $ip, $uri,
      $url, $s2s_call_reason, $http_version,
      $http_method, $cookie_hmac )
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
            } else {
                $logger->expects($this->never())
                    ->method($level);
            }
        }

        return $logger;
    }
  }
?>
