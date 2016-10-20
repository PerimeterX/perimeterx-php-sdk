<?php

namespace Perimeterx\Tests;

use Perimeterx\PerimeterxContext;
use Perimeterx\PerimeterxCookieValidator;
use Perimeterx\Tests\Fixtures\PerimeterxContextGoodCookie;
use PHPUnit\Framework\TestCase;

class PerimeterxCookieTest extends TestCase
{
    protected function setUp()
    {
        global $hmac_matches_count;
        $hmac_matches_count = 0;
    }

    public function testGoodCookie() {
        $this->markTestSkipped();
        $ctx = new PerimeterxContextGoodCookie();
        $pxCookieValidator = new PerimeterxCookieValidator($ctx, [
            'cookie_key' => PX_COOKIE_KEY,
            'encryption_enabled' => true,
            'blocking_score' => 60
        ]);

        $verify = $pxCookieValidator->verify();
        $this->assertTrue($verify);
    }

    public function data_is_cookie_ok()
    {
        $cookie_for_context_a_with_ip_hmac = $this->getCookieDouble(
            '1476900333',
            10,
            20,
            'ce62e82c027da4ee82d7f34740f72f2c',
            '70b9aaf9ec559a110e3a794e43e79200',
            'f92f25cc2c07ed5d4f5eb0b2246fb633d80c44e5710afe144617ec138eb8b4a8'
        );
        $cookie_for_context_a_without_ip_hmac = $this->getCookieDouble(
            '1476900333',
            10,
            20,
            'ce62e82c027da4ee82d7f34740f72f2c',
            '70b9aaf9ec559a110e3a794e43e79200',
            'ec874d9b52212f4e22c0f79d67860940b7f6e6354621c094df7f18ab32f05334'
        );

        $context_a = $this->getPerimeterxContextDouble('127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.33 Safari/537.36');
        $context_b = $this->getPerimeterxContextDouble('127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.33 Safari/537.36');

        return [
            'matching_hmac_for_current_cookie_without_ip' => [$cookie_for_context_a_without_ip_hmac, clone $context_a, true, 1],
            'matching_hmac_for_backwards_cookie_with_ip' => [$cookie_for_context_a_with_ip_hmac, clone $context_a, true, 2],
            'non_matching_hmac_for_current_cookie_without_ip' => [$cookie_for_context_a_without_ip_hmac, clone $context_b, false, 2],
            'non_matching_hmac_for_backwards_cookie_with_ip' => [$cookie_for_context_a_with_ip_hmac, clone $context_b, false, 2],
        ];
    }

    /**
     * @dataProvider data_is_cookie_ok
     *
     * @return void
     */
    public function test_is_cookie_ok($cookie, $pxCtx, $expected_results, $expected_hmac_matches_count)
    {
        $cookieSecret = '0ab47f54d7ce5a8e477e86c036ceeb7b';

        $is_cookie_ok = PerimeterxCookieValidator::is_cookie_ok($cookie, $pxCtx, $cookieSecret);

        $this->assertEquals($expected_results, $is_cookie_ok);

        global $hmac_matches_count;
        $this->assertEquals($expected_hmac_matches_count, $hmac_matches_count);
    }

    private function getPerimeterxContextDouble($ip, $user_agent)
    {
        $pxCtx = $this->getMockBuilder(PerimeterxContext::class)
            ->setMethods(['getIp', 'getUserAgent'])
            ->disableOriginalConstructor()
            ->getMock();
        $pxCtx->expects($this->any())
            ->method('getIp')
            ->willReturn($ip);
        $pxCtx->expects($this->any())
            ->method('getUserAgent')
            ->willReturn($user_agent);

        return $pxCtx;
    }

    private function getCookieDouble($c_time, $c_score_a, $c_score_b, $c_uuid, $c_vid, $c_hmac)
    {
        $cookie = new \stdClass();
        $cookie->s = new \stdClass();
        $cookie->t = $c_time;
        $cookie->s->a = $c_score_a;
        $cookie->s->b = $c_score_b;
        $cookie->u = $c_uuid;
        $cookie->v = $c_vid;
        $cookie->h = $c_hmac;

        return $cookie;
    }

}
