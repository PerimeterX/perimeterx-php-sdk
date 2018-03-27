<?php


use Perimeterx\PerimeterxContext;
use Perimeterx\PerimeterxCookieValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class PerimeterxCookieValidatorTest extends PHPUnit_Framework_TestCase
{

    // randomly generated fake values
    const COOKIE_KEY = '549Z5UsasvfmVS6kAR3r4ydPnQdnnW4Gcwk35hj5tatZ5B2dqjrQvMMyLAJN5de3';
    const COOKIE_UUID = '9e70ed8b-c205-4a9d-bf3a-e92a945be600';
    const COOKIE_VID = '69521dce-ab65-11e6-80f5-76304dec7eb7';
    const IP = '10.10.10.10';
    const USER_AGENT = 'Mozilla';

    // cookie tests
    public function testNoCookie() {

        $pxCookie = null;
        $userAgent = 'Mozilla';
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

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

        $pxCookie = 'this is not base64 encoded json';
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

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
        $cookie_vid = null;
        $cookie_hmac = 'something';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $pxCookie = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
        );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);
        $pxCtx->expects($this->any())
            ->method('getIp')
            ->willThrowException(new \Exception('inject an exception, not likely to come from getIp however'));

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
        $cookie_hmac = 'something';
        $cookie_score_a = 0;
        $cookie_score_b = 100;

        $pxCookie = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
        );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie evaluation ended successfully, risk score: $cookie_score_b", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertTrue($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score_b,
            null,
            'cookie_high_score'
        );
    }

    public function testCookieExpired() {

        $cookie_time = (time() - 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_hmac = 'something';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $decodedCookie = $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b);
         $decodedCookieString = json_encode($decodedCookie);

        $pxCookie = $this->encodeCookie(
            $decodedCookie
        );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

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
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score_b,
            'cookie_expired',
            null
        );
    }

    public function testCookieHmacInvalid() {

        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_hmac = 'something';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $decodedCookie = $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b);
        $decodedCookieString = json_encode($decodedCookie);

        $pxCookie = $this->encodeCookie(
            $decodedCookie
        );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie HMAC validation failed, value: $decodedCookieString, user-agent: $userAgent", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score_b,
            'cookie_validation_failed',
            null
        );
    }

    public function testCookieHmacValid() {

        // far future, consistent cookie, 2116-11-14T00:00:00Z
        $cookie_time = '4634841600000';
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        // calculated at time of writing
        $cookie_hmac = '826bb9324795dd2e621e133372a627f7b5d9523978fd1c3337389b9fa1f5cbc7';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $pxCookie = $this->encodeCookie(
                $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
            );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie evaluation ended successfully, risk score: $cookie_score_b", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertTrue($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score_b,
            null,
            null
        );
    }

    public function testCookieVerificationFailedOnSensitiveRoute() {

        // far future, consistent cookie, 2116-11-14T00:00:00Z
        $cookie_time = '4634841600000';
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        // calculated at time of writing
        $cookie_hmac = '826bb9324795dd2e621e133372a627f7b5d9523978fd1c3337389b9fa1f5cbc7';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $pxCookie = $this->encodeCookie(
                $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
            );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip, true);

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

    public function testCookieBackwardsCompatibleHmacValid() {

        // far future, consistent cookie, 2116-11-14T00:00:00Z
        $cookie_time = '4634841600000';
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        // calculated at time of writing
        $cookie_hmac = '1eb773517a16c0e13c5ed826b34546db2bf61b249478ea9faed7d78e29ffa954';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $pxCookie = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
        );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie evaluation ended successfully, risk score: $cookie_score_b", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertTrue($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score_b,
            null,
            null
        );
    }

    // // mobile sdk cookie header tests

    public function testNoMobileHeaderCookie() {
        $pxCookie = 1;
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip, false, "header");

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', 'Mobile special token: 1')
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, null, 'mobile_error_1', null);
    }

    public function testBadlyEncodedMobileHeaderCookie() {

        $pxCookie = 'this is not base64 encoded json';
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip, false, "header");

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

    public function testMissingMobileHeaderCookieContentsThrowsException() {

        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = null;
        $cookie_hmac = 'something';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $pxCookie = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
        );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip, false, "header");
        $pxCtx->expects($this->any())
            ->method('getIp')
            ->willThrowException(new \Exception('inject an exception, not likely to come from getIp however'));

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

        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_hmac = 'something';
        $cookie_score_a = 0;
        $cookie_score_b = 100;

        $pxCookie = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
        );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip, false, "header");

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "ookie evaluation ended successfully, risk score: $cookie_score_b", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertTrue($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score_b,
            null,
            'cookie_high_score'
        );
    }

    public function testMobileHeaderCookieExpired() {

        $cookie_time = (time() - 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_hmac = 'something';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $decodedCookie = $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b);
        $decodedCookieString = json_encode($decodedCookie);

        $pxCookie = $this->encodeCookie(
            $decodedCookie
        );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip, false, "header");

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
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score_b,
            'cookie_expired',
            null
        );
    }

    public function testMobileHeaderCookieHmacInvalid() {

        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_hmac = 'something';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $cookieObject =  $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b);
        $decryptedCookie = json_encode($cookieObject);

        $pxCookie = $this->encodeCookie(
            $cookieObject
        );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip, false, "header");

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
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score_b,
            'cookie_validation_failed',
            null
        );
    }

    public function testMobileHeaderCookieHmacValid() {

        // far future, consistent cookie, 2116-11-14T00:00:00Z
        $cookie_time = '4634841600000';
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        // calculated at time of writing
        $cookie_hmac = '618f9835afa82c8c083c844f41b1c777d444e92a4e51981b745ab68d9041a055'; // hmac without user agent
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $pxCookie = $this->encodeCookie(
                $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
            );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip, false, "header");

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "Cookie evaluation ended successfully, risk score: $cookie_score_b", 1),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertTrue($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score_b,
            null,
            null
        );
    }

    public function testMobileHeaderCookieVerificationFailedOnSensitiveRoute() {

        // far future, consistent cookie, 2116-11-14T00:00:00Z
        $cookie_time = '4634841600000';
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        // calculated at time of writing
        $cookie_hmac = '618f9835afa82c8c083c844f41b1c777d444e92a4e51981b745ab68d9041a055';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $pxCookie = $this->encodeCookie(
                $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
            );
        $userAgent = self::USER_AGENT;
        $ip = self::IP;
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip, true, "header");

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
        $this->assertEquals($s2s_call_reason, $pxCtx->getS2SCallReason());
        $this->assertEquals($block_reason, $pxCtx->getBlockReason());
    }

    private function getMockLogger($expected_level = null, $expected_message = null, $msgIndex = 0)
    {
        $levels = ['debug', 'info', 'warning', 'error'];
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

    private function createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
    {
        $cookie = new \stdClass();
        $cookie->s = new \stdClass();
        $cookie->s->a = $cookie_score_a;
        $cookie->s->b = $cookie_score_b;
        $cookie->t = $cookie_time;
        $cookie->v = $cookie_vid;
        $cookie->u = $cookie_uuid;
        $cookie->h = $cookie_hmac;

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
    private function getPxContext($pxCookie, $userAgent, $ip, $sensitive_route = false, $cookie_origin = "cookie")
    {
        $pxCtx = $this->getMockBuilder(PerimeterxContext::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPxCookie', 'getUserAgent', 'getIp','isSensitiveRoute', 'getCookieOrigin'])
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
            ->method('isSensitiveRoute')
            ->willReturn($sensitive_route);
        $pxCtx->expects($this->any())
            ->method('getCookieOrigin')
            ->willReturn($cookie_origin);
        return $pxCtx;
    }

}
