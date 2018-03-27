<?php


use Perimeterx\PerimeterxContext;
use Perimeterx\PerimeterxCookieValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class PerimeterxCookieV3ValidatorTest extends PHPUnit_Framework_TestCase
{

    // randomly generated fake values
    const COOKIE_KEY = '549Z5UsasvfmVS6kAR3r4ydPnQdnnW4Gcwk35hj5tatZ5B2dqjrQvMMyLAJN5de3';
    const COOKIE_UUID = '9e70ed8b-c205-4a9d-bf3a-e92a945be600';
    const COOKIE_VID = '69521dce-ab65-11e6-80f5-76304dec7eb7';
    const USER_AGENT = 'Mozilla';

    // cookie tests
    public function testNoCookie() {

        $pxCookie = null;
        $userAgent = 'Mozilla';
        $pxCtx = $this->getPxContext($pxCookie, $userAgent);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', 'Cookie is missing')
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, null, 'no_cookie', null);
    }

    public function testBadlyEncodedCookie() {

        $pxCookie = '123:this is not base64 encoded json';
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie decryption failed, value: $pxCookie", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, null, 'cookie_decryption_failed', null);
    }

    public function testMissingCookieContentsThrowsException() {

        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 0;

        $pxPayload = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score, null)
        );
        $pxCookie = $this->signCookie($pxPayload).":".$pxPayload;
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie decryption failed, value: $pxCookie", 1)
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
    }

    public function testCookieHighScore() {

        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 100;

        $pxPayload = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score)
        );
        $pxCookie = $this->signCookie($pxPayload).":".$pxPayload;
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie evaluation ended successfully, risk score: $cookie_score", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertTrue($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score,
            null,
            'cookie_high_score'
        );
    }

    public function testCookieExpired() {

        $cookie_time = (time() - 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 0;

        $decodedCookie = $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score);
        $decodedCookieString = json_encode($decodedCookie);

        $pxPayload = $this->encodeCookie(
            $decodedCookie
        );
        $pxCookie = $this->signCookie($pxPayload).":".$pxPayload;
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie TTL is expired, value: $decodedCookieString, age: ", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score,
            'cookie_expired',
            null
        );
    }

    public function testCookieHmacInvalid() {

        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 0;

        $cookieObject =  $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score);
        $decryptedCookie = json_encode($cookieObject);

        $pxPayload = $this->encodeCookie(
            $cookieObject
        );
        $pxCookie = "fakehmacyay".":".$pxPayload;
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie HMAC validation failed, value: $decryptedCookie, user-agent: $userAgent", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext(
            $pxCtx,
             $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score,
            'cookie_validation_failed',
            null
        );
    }

    public function testCookieHmacValid() {

        // far future, consistent cookie, 2116-11-14T00:00:00Z
        $cookie_time = '4634841600000';
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 0;

        $pxPayload = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score)
        );
        $pxCookie = $this->signCookie($pxPayload).":".$pxPayload;

        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie evaluation ended successfully, risk score: $cookie_score", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertTrue($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score,
            null,
            null
        );
    }

    public function testCookieVerificationFailedOnSensitiveRoute() {

        // far future, consistent cookie, 2116-11-14T00:00:00Z
        $cookie_time = '4634841600000';
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 0;

        $pxPayload = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score)
        );
        $pxCookie = $this->signCookie($pxPayload).":".$pxPayload;

        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, true);

        // Modify pxCtx to return true on sensitive route
        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Sensitive route match, sending Risk API. path: ", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertEquals("sensitive_route", $pxCtx->getS2SCallReason());
    }

    public function testEncryptedCookieDecryption() {
        // far future encrypted cookie
        $pxCookie = "eb34be587303f1ac7b04bed5fd5388bc3d2a0ad29a014ae6823ebfe27ba59ed4:2EK7x6pHyMwy6pB9PmYKhX7YaiAf3aay2MwzZhS+x8G/htGX6xfjbLt8Gkn3cgggpD/6ykXk5XjZ7UG9TePOiQ==:1000:8HgbjMrrYXbYhVwh8e0+Vnsg051OvqpKfYkZjzPZLIKyVCjwL5ufPj8s3BXl1bBTdhwqRXUZnq1vJuBpOoTBdrC+AUL/kYqLmW6JyF+MpjfIdKaSqGZag2qZRFGMJSSf3EBVZevInEbZMoNBjLAsHnbJAToZ2+ejIIIM6XmBGqo=";
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, false);
        $pxConfig = [
            'encryption_enabled' => true,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie evaluation ended successfully, risk score: 0", 1)
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertTrue($v->verify());
    }

    // mobile sdk token testings
    public function testNoMobileHeaderCookie() {
        $pxCookie = 1;
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, false, "header");

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', 'Mobile special token: 1')
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, null, 'no_cookie', null);
    }

    public function testBadlyEncodedMobileHeaderCookie() {

        $pxCookie = '123:this is not base64 encoded json';
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, false, "header");

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie decryption failed, value: $pxCookie", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, null, 'cookie_decryption_failed', null);
    }

    public function testMissingCookieContentsMobileHeaderThrowsException() {

        $cookie_origin="header";
        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 0;

        $pxPayload = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score, null)
        );
        $pxCookie = $this->signCookie($pxPayload, $cookie_origin).":".$pxPayload;
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, false, $cookie_origin);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie decryption failed, value: $pxCookie", 1)
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
    }

    public function testMobileHeaderCookieHighScore() {

        $cookie_origin="header";
        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 100;

        $pxPayload = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score)
        );
        $pxCookie = $this->signCookie($pxPayload, $cookie_origin).":".$pxPayload;
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, false, $cookie_origin);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "ookie evaluation ended successfully, risk score: $cookie_score", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertTrue($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score,
            null,
            'cookie_high_score'
        );
    }

    public function testMobileHeaderCookieExpired() {

        $cookie_origin="header";
        $cookie_time = (time() - 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 0;

        $decodedCookie = $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score);
        $decodedCookieString = json_encode($decodedCookie);

        $pxPayload = $this->encodeCookie(
            $decodedCookie
        );
        $pxCookie = $this->signCookie($pxPayload, $cookie_origin).":".$pxPayload;
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, false, $cookie_origin);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie TTL is expired, value: $decodedCookieString, age: ", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score,
            'cookie_expired',
            null
        );
    }

    public function testMobileHeadertCookieHmacInvalid() {

        $cookie_origin="header";
        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 0;

        $cookieObject =  $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score);
        $decryptedCookie = json_encode($cookieObject);

        $pxPayload = $this->encodeCookie(
            $cookieObject
        );
        $pxCookie = "fakehmacyay".":".$pxPayload;
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, false, $cookie_origin);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie HMAC validation failed, value: $decryptedCookie, user-agent: $userAgent", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext(
            $pxCtx,
             $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score,
            'cookie_validation_failed',
            null
        );
    }

    public function testMobileHeaderCookieHmacValid() {

        // far future, consistent cookie, 2116-11-14T00:00:00Z
        $cookie_origin="header";
        $cookie_time = '4634841600000';
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 0;

        $pxPayload = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score)
        );
        $pxCookie = $this->signCookie($pxPayload, $cookie_origin).":".$pxPayload;

        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, false, $cookie_origin);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie evaluation ended successfully, risk score: $cookie_score", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertTrue($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score,
            null,
            null
        );
    }

    public function testMobileHeaderCookieVerificationFailedOnSensitiveRoute() {

        $cookie_origin="header";
        // far future, consistent cookie, 2116-11-14T00:00:00Z
        $cookie_time = '4634841600000';
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_score = 0;

        $pxPayload = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score)
        );
        $pxCookie = $this->signCookie($pxPayload, $cookie_origin).":".$pxPayload;

        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, true, $cookie_origin);

        // Modify pxCtx to return true on sensitive route
        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Sensitive route match, sending Risk API. path: ", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertEquals("sensitive_route", $pxCtx->getS2SCallReason());
    }

    public function testEncryptedTokenDecryption() {
        // far future encrypted cookie
        $cookie_origin="header";
        $pxCookie = "8d4a0f41e65af5088266f1eb0db7bd4266309d3c471c097aea538e17c1ebe82d:mmjSIpdOpbZYsDjXe0vAieBbrG7k8JVJO2+9itMXUoZfugSlvRIqK4BUKPDAd2gftZtbwU0eA5LZXHf8VURI/w==:1000:KFCP3EU4qtGhRab+OgIYeg0SXhXWWfmSvmTK6+4J5vwN73/IVx/BkcRzC1inFxJ2jCHr+KqcQxEpcqyO1E91l2Wk38ddADYzuog3KhbsV9j2wXldnX0zWkc7dgnRHc8xJuck4NfrZivdfQNA0AC4k+z0sasqwRWQ5Eq1mjnIwf8=";
        $userAgent = self::USER_AGENT;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, false, $cookie_origin);
        $pxConfig = [
            'encryption_enabled' => true,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie evaluation ended successfully, risk score: 0", 1)
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertTrue($v->verify());
    }

    // private functions
    private function getMockLogger($expected_level = null, $expected_message = null, $msgIndex = 0)
    {
        $levels = ['info', 'debug', 'warning', 'error'];
        $logger = $this->createMock(AbstractLogger::class);

        foreach ($levels as $level) {
            if ($expected_level === $level) {
                $logger->expects($this->at($msgIndex))
                    ->method($expected_level)
                    ->with($this->stringContains($expected_message));
            } else {
                $logger->expects($this->never())
                    ->method($level);
            }
        }

        return $logger;
    }

    /**
     * assertPxContext
     *
     * @param PerimeterxContext $pxCtx
     * @param \stdClass                     $decoded_cookie
     * @param string                        $uuid
     * @param string                        $vid
     * @param int                           $score
     * @param string                        $s2s_call_reason
     * @param string                        $block_reason
     *
     * @return void
     */
    private function assertPxContext($pxCtx, $decoded_cookie, $uuid, $vid, $score, $s2s_call_reason, $block_reason) {
        $this->assertEquals($decoded_cookie, $pxCtx->getDecodedCookie());
        $this->assertEquals($uuid, $pxCtx->getUuid());
        $this->assertEquals($vid, $pxCtx->getVid());
        $this->assertEquals($score, $pxCtx->getScore());
        // $this->assertEquals($s2s_call_reason, $pxCtx->getS2SCallReason());
        // $this->assertEquals($block_reason, $pxCtx->getBlockReason());
    }

    private function createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_score, $cookie_action = "c")
    {
        $cookie = new \stdClass();
        $cookie->s = $cookie_score;
        $cookie->t = $cookie_time;
        $cookie->v = $cookie_vid;
        $cookie->u = $cookie_uuid;
        $cookie->a = $cookie_action;

        return $cookie;
    }

    private function encodeCookie($cookie)
    {
        $data_str = json_encode(json_encode($cookie));

        return base64_encode($data_str);
    }


    /**
     * getPxCtx
     *
     * @param $pxCookie
     * @param $userAgent
     * @param $ip
     *
     * @return \Perimeterx\PerimeterxContext
     */
    private function getPxContext($pxCookie, $userAgent, $sensitive_route = false, $cookie_origin = "cookie")
    {
        $pxCtx = $this->getMockBuilder(PerimeterxContext::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPxCookie', 'getUserAgent', 'getIp','isSensitiveRoute', 'getCookieOrigin', 'getPxCookies'])
            ->getMock();
        $pxCtx->expects($this->any())
            ->method('getPxCookie')
            ->willReturn($pxCookie);
        $pxCtx->expects($this->any())
            ->method('getUserAgent')
            ->willReturn($userAgent);
        $pxCtx->expects($this->any())
            ->method('isSensitiveRoute')
            ->willReturn($sensitive_route);
        $pxCtx->expects($this->any())
            ->method('getCookieOrigin')
            ->willReturn($cookie_origin);
        $pxCtx->expects($this->any())
            ->method("getPxCookies")
            ->willReturn(array('v3' => 'aaaaa')); // mocking an entry to cookies array that will trigger V3 cookie/token
        return $pxCtx;
    }

    private function signCookie($hmac_str, $cookie_origin = "cookie")
    {
        $hmac = $cookie_origin == "cookie" ? $hmac_str.self::USER_AGENT : $hmac_str;
        return hash_hmac('sha256', $hmac, self::COOKIE_KEY);
    }
}