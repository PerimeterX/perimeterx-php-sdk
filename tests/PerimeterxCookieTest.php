<?php


use Psr\Log\AbstractLogger;
use Perimeterx\PerimeterxCookieValidator;
use Perimeterx\Tests\Fixtures\PerimeterxContextGoodCookie;

class PerimeterxCookieTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
    }

    public function testGoodCookie() {
        $ctx = new PerimeterxContextGoodCookie();
        $pxCookieValidator = new PerimeterxCookieValidator($ctx, [
            'cookie_key' => PX_COOKIE_KEY,
            'encryption_enabled' => true,
            'blocking_score' => 60,
            'logger' => $this->getMockLogger('info', 'cookie ok')
        ]);

        $verify = $pxCookieValidator->verify();
        $this->assertTrue($verify);
    }

    private function getMockLogger($expected_level = null, $expected_message = null)
    {
        $levels = ['', 'warning', 'error'];
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