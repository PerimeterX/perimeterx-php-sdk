<?php


use Perimeterx\PerimeterxContext;
use Perimeterx\PerimeterxCookieValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class PerimeterxOriginalTokenValidatorTest extends PHPUnit_Framework_TestCase
{
    // randomly generated fake values
    const COOKIE_KEY = '549Z5UsasvfmVS6kAR3r4ydPnQdnnW4Gcwk35hj5tatZ5B2dqjrQvMMyLAJN5de3';
    const COOKIE_UUID = '9e70ed8b-c205-4a9d-bf3a-e92a945be600';
    const COOKIE_VID = '69521dce-ab65-11e6-80f5-76304dec7eb7';
    const SALT = '12345678123456781234567812345678';

    public function testValidOriginalTokenExtraction() {
        $pxToken = '2';
        $token_time = (time() + 1000) * 1000;
        $token_uuid = self::COOKIE_UUID;
        $token_vid = self::COOKIE_VID;
        $token_score = 0;

        $decodedToken = $this->createCookie($token_time, $token_vid, $token_uuid, $token_score);

        $pxPayload = $this->encodeCookie($decodedToken);

        $pxOriginalToken = "3:".$this->signCookie($pxPayload).":".$pxPayload;
        $pxCtx = $this->getPxContext($pxToken, $pxOriginalToken);

        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', 'Original token found, evaluating', 1)
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, $decodedToken, $token_uuid, $token_vid, null);
    }

    public function testBadOriginalTokenExtraction() {
        $pxToken = '2';
        $pxOriginalToken = 'aaaaa';

        $pxCtx = $this->getPxContext($pxToken, $pxOriginalToken);
        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', 'Original token found, evaluating', 1)
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, "cookie_decryption_failed");
    }

    public function testInvalidOriginalTokenExtraction() {
        $pxToken = '2';
        $token_time = (time() + 1000) * 1000;
        $token_uuid = self::COOKIE_UUID;
        $token_vid = self::COOKIE_VID;
        $token_score = 0;

        $decodedToken = $this->createCookie($token_time, $token_vid, $token_uuid, $token_score);

        $pxPayload = $this->encodeCookie($decodedToken);

        $pxOriginalToken = "3:".$this->signCookie($pxPayload.'111111').":".$pxPayload;

        $pxCtx = $this->getPxContext($pxToken, $pxOriginalToken);
        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', 'Original token found, evaluating', 1)
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, "cookie_validation_failed");
    }

    public function testInvalidOriginalTokenKey() {
        $pxToken = '2';
        $token_time = (time() + 1000) * 1000;
        $token_uuid = self::COOKIE_UUID;
        $token_vid = self::COOKIE_VID;
        $token_score = 0;

        $decodedToken = $this->createCookie($token_time, $token_vid, $token_uuid, $token_score);

        $pxPayload = $this->encodeCookie($decodedToken);

        $pxOriginalToken = "3:3:".$this->signCookie($pxPayload).":".$pxPayload;

        $pxCtx = $this->getPxContext($pxToken, $pxOriginalToken);
        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', 'Original token found, evaluating', 1)
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, "cookie_decryption_failed");
    }

    public function testOrigitalTokenWithoutKey() {
        $pxToken = '2';
        $token_time = (time() + 1000) * 1000;
        $token_uuid = self::COOKIE_UUID;
        $token_vid = self::COOKIE_VID;
        $token_score = 0;

        $decodedToken = $this->createCookie($token_time, $token_vid, $token_uuid, $token_score);

        $pxPayload = $this->encodeCookie($decodedToken);

        $pxOriginalToken = $this->signCookie($pxPayload).":".$pxPayload;

        $pxCtx = $this->getPxContext($pxToken, $pxOriginalToken);
        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', 'Original token found, evaluating', 1)
        ];

        $v = new PerimeterxCookieValidator($pxCtx, $pxConfig);

        $this->assertFalse($v->verify());
        $this->assertPxContext($pxCtx, null, null, null, "cookie_decryption_failed");
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
     * @param \stdClass                     $decoded_token
     * @param string                        $uuid
     * @param string                        $vid
     * @param int                           $score
     * @param string                        $s2s_call_reason
     * @param string                        $block_reason
     *
     * @return void
     */
    private function assertPxContext($pxCtx, $decoded_token, $uuid, $vid, $original_token_error) {
        if (!is_null($uuid)) {
            $this->assertEquals($uuid, $pxCtx->getOriginalTokenUuid());
        }
        if (!is_null($vid)) {
            $this->assertEquals($vid, $pxCtx->getVid());
        }
        if (!is_null($decoded_token)) {
            $this->assertEquals($decoded_token, $pxCtx->getDecodedOriginalToken());
        }

        $this->assertEquals($original_token_error, $pxCtx->getOriginalTokenError());
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

    private function signCookie($hmac_str) {
        return hash_hmac('sha256', $hmac_str, self::COOKIE_KEY);
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
    private function getPxContext($pxToken, $pxOriginalToken, $cookie_origin = "header")
    {
        $pxCtx = $this->getMockBuilder(PerimeterxContext::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPxCookie', 'getOriginalToken', 'getCookieOrigin'])
            ->getMock();
        $pxCtx->expects($this->any())
            ->method('getPxCookie')
            ->willReturn($pxToken);
        $pxCtx->expects($this->any())
            ->method('getOriginalToken')
            ->willReturn($pxOriginalToken);
        $pxCtx->expects($this->any())
            ->method('getCookieOrigin')
            ->willReturn($cookie_origin);
        return $pxCtx;
    }
}