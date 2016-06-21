<?php

namespace Perimeterx\Tests;

use Perimeterx\PerimeterxCookieValidator;
use Perimeterx\Tests\Fixtures\PerimeterxContextGoodCookie;
use PHPUnit\Framework\TestCase;

class PerimeterxCookieTest extends TestCase
{
    protected function setUp()
    {
    }
    
    public function testGoodCookie() {
        $ctx = new PerimeterxContextGoodCookie();
        $pxCookieValidator = new PerimeterxCookieValidator($ctx, [
            'cookie_key' => PX_COOKIE_KEY,
            'encryption_enabled' => true,
            'blocking_score' => 60
        ]);

        $verify = $pxCookieValidator->verify();
        $this->assertTrue($verify);
    }
}
