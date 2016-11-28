<?php

namespace Perimeterx\Tests;

use Perimeterx\PerimeterxContext;
use Perimeterx\PerimeterxCookieValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class PerimeterxCookieValidatorTest extends TestCase
{

    // randomly generated fake values
    const COOKIE_KEY = '549Z5UsasvfmVS6kAR3r4ydPnQdnnW4Gcwk35hj5tatZ5B2dqjrQvMMyLAJN5de3';
    const COOKIE_UUID = '9e70ed8b-c205-4a9d-bf3a-e92a945be600';
    const COOKIE_VID = '69521dce-ab65-11e6-80f5-76304dec7eb7';

    public function testNoCookie() {

        $pxCookie = null;
        $userAgent = 'Mozilla';
        $ip = '10.10.10.10';
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger(),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, null, 'no_cookie', null);
    }

    public function testBadlyEncodedCookie() {

        $pxCookie = 'this is not base64 encoded json';
        $userAgent = 'Mozilla';
        $ip = '10.10.10.10';
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('warning', 'invalid cookie'),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, null, 'cookie_decryption_failed', null);
    }

    public function testMissingCookieContentsThrowsException() {

        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = self::COOKIE_UUID;
        $cookie_vid = self::COOKIE_VID;
        $cookie_hmac = 'something';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $pxCookie = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
        );
        $userAgent = 'Mozilla';
        $ip = '10.10.10.10';
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);
        $pxCtx->expects($this->any())
            ->method('getIp')
            ->willThrowException(new \Exception('inject an exception, not likely to come from getIp however'));

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('error', 'exception while verifying cookie'),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext(
            $pxCtx,
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b),
            $cookie_uuid,
            $cookie_vid,
            $cookie_score_b,
            'cookie_decryption_failed',
            null
        );
    }

    public function testInvalidCookieContents() {

        $cookie_time = (time() + 1000) * 1000;
        $cookie_uuid = null;
        $cookie_vid = self::COOKIE_VID;
        $cookie_hmac = 'something';
        $cookie_score_a = 0;
        $cookie_score_b = 0;

        $pxCookie = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
        );
        $userAgent = 'Mozilla';
        $ip = '10.10.10.10';
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => 'asdf',
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('warning', 'invalid cookie'),
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, null, 'cookie_decryption_failed', null);
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
        $userAgent = 'Mozilla';
        $ip = '10.10.10.10';
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('info', 'cookie high score'),
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

        $pxCookie = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
        );
        $userAgent = 'Mozilla';
        $ip = '10.10.10.10';
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('info', 'cookie expired'),
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

        $pxCookie = $this->encodeCookie(
            $this->createCookie($cookie_time, $cookie_vid, $cookie_uuid, $cookie_hmac, $cookie_score_a, $cookie_score_b)
        );
        $userAgent = 'Mozilla';
        $ip = '10.10.10.10';
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('warning', 'cookie invalid hmac'),
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
        $userAgent = 'Mozilla';
        $ip = '10.10.10.10';
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('info', 'cookie ok'),
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
        $userAgent = 'Mozilla';
        $ip = '10.10.10.10';
        $pxCtx = $this->getPxContext($pxCookie, $userAgent, $ip);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('info', 'cookie ok'),
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
    private function getPxContext($pxCookie, $userAgent, $ip)
    {
        $pxCtx = $this->getMockBuilder(PerimeterxContext::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPxCookie', 'getUserAgent', 'getIp'])
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

        return $pxCtx;
    }

}
