<?php


use Perimeterx\PerimeterxContext;
use Perimeterx\PerimeterxLogger;
use Perimeterx\PerimeterxCookieValidator;
use Perimeterx\PerimeterxDataEnrichment;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class PerimeterxDataEnrichmentTest extends PHPUnit_Framework_TestCase {
    const COOKIE_KEY = '549Z5UsasvfmVS6kAR3r4ydPnQdnnW4Gcwk35hj5tatZ5B2dqjrQvMMyLAJN5de3';

    public function testNoPxdeCookie() {
        $pxCtx = $this->getPxContext(null);
        $pxde = $pxCtx->getDataEnrichment();
        $this->assertFalse(isset($pxde));
    }

    public function testValidEnrichment() {
        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "", 1),
        ];

        $time = (time() + 1000) * 1000;
        $pxde = $this->createEnrichment($time);
        
        $encodedEnrichment = base64_encode(json_encode($pxde));
        $hash_digest = hash_hmac('sha256', $encodedEnrichment, $pxConfig['cookie_key']);
        $cookie = $hash_digest . ':' . $encodedEnrichment;
        
        $pxCtx = $this->getPxContext($cookie);
        PerimeterxDataEnrichment::processDataEnrichment($pxCtx, $pxConfig);
        
        $this->assertTrue($pxCtx->getDataEnrichmentVerified());
        $enrichmentObj = $pxCtx->getDataEnrichment();
        $this->assertTrue($enrichmentObj->timestamp == $time);
        $this->assertTrue($enrichmentObj->ipc_id == [1,2]);
    }

    public function testNonValidEnrichment() {
        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "", 1)
        ];
        
        $time = (time() + 1000) * 1000;
        $pxde = $this->createEnrichment($time);
        
        $encodedEnrichment = base64_encode(json_encode($pxde));
        $hash_digest = hash_hmac('sha256', $encodedEnrichment, 'non valid key');
        $cookie = $hash_digest . ':' . $encodedEnrichment;
        
        $pxCtx = $this->getPxContext($cookie);
        PerimeterxDataEnrichment::processDataEnrichment($pxCtx, $pxConfig);
        $this->assertFalse($pxCtx->getDataEnrichmentVerified());
    }

    public function testNotBase64EncodedEnrichment() {
        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "", 1)
        ];
        
        $time = (time() + 1000) * 1000;
        
        $encodedEnrichment = '';
        $hash_digest = hash_hmac('sha256', $encodedEnrichment, $pxConfig['cookie_key']);
        $cookie = $hash_digest . ':' . $encodedEnrichment;
        
        $pxCtx = $this->getPxContext($cookie);
        PerimeterxDataEnrichment::processDataEnrichment($pxCtx, $pxConfig);
        $this->assertTrue($pxCtx->getDataEnrichmentVerified());
        $enrichmentObj = $pxCtx->getDataEnrichment();
        $this->assertFalse(isset($enrichmentObj));
    }

    public function testNotJsonEnrichment() {
        $pxConfig = [
            'encryption_enabled' => false,
            'cookie_key' => self::COOKIE_KEY,
            'blocking_score' => 70,
            'logger' => $this->getMockLogger('debug', "", 1)
        ];
        
        $time = (time() + 1000) * 1000;
        
        $encodedEnrichment = 'non json enrichment';
        $hash_digest = hash_hmac('sha256', $encodedEnrichment, $pxConfig['cookie_key']);
        $cookie = $hash_digest . ':' . $encodedEnrichment;
        
        $pxCtx = $this->getPxContext($cookie);
        PerimeterxDataEnrichment::processDataEnrichment($pxCtx, $pxConfig);
        $this->assertTrue($pxCtx->getDataEnrichmentVerified());
        $enrichmentObj = $pxCtx->getDataEnrichment();
        $this->assertFalse(property_exists($enrichmentObj, 'timestamp'));
    }

    private function createEnrichment($time) {
        $pxde = new stdClass;
        $pxde->timestamp = $time;
        $pxde->ipc_id = [1,2];
        return $pxde;
    }

    private function getPxContext($pxCookie)
    {
        $pxCtx = $this->getMockBuilder(PerimeterxContext::class)
            ->disableOriginalConstructor()
            ->setMethods(['getDataEnrichmentCookie'])
            ->getMock();
        $pxCtx->expects($this->any())
            ->method('getDataEnrichmentCookie')
            ->willReturn($pxCookie);
        return $pxCtx;
    }

    private function getMockLogger($expected_level = null, $expected_message = null)
    {
        $levels = ['', 'warning', 'error'];
        $logger = $this->createMock(AbstractLogger::class);

        return $logger;
    }
}