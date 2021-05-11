<?php

use Perimeterx\PerimeterxFieldExtractor;
use Perimeterx\PerimeterxFieldExtractorManager;
use Psr\Log\AbstractLogger;

class PerimeterxFieldExtractorManagerTest extends PHPUnit_Framework_TestCase
{
    const LOGIN_REQUEST_URI = "/login";
    const LOGIN_REQUEST_METHOD = "POST";
    const EXPECTED_RETURN_VALUE = "returnValue";
    
    private $fieldExtractorManager;

    public function setUp() {
        $mapKey = PerimeterxFieldExtractorManager::generateMapKey(self::LOGIN_REQUEST_URI, self::LOGIN_REQUEST_METHOD);
        $mockExtractor = $this->createMock(PerimeterxFieldExtractor::class);
        $mockExtractor
            ->method('extractFields')
            ->willReturn(self::EXPECTED_RETURN_VALUE);
        $extractionMap = [ $mapKey => $mockExtractor ];
        $this->fieldExtractorManager = new PerimeterxFieldExtractorManager($extractionMap, $this->getMockLogger());
    }

    /**
     * @dataProvider provideExtractCredentialsData
     */
    public function testExtractCredentials($method, $uri, $expectedReturnValue) {
        self::initializeRequest($method, $uri);
        $actualReturnValue = $this->fieldExtractorManager->extractFields();
        $this->assertEquals($expectedReturnValue, $actualReturnValue);
    }

    public function provideExtractCredentialsData() {
        $wrongUri = "/login2";
        $wrongMethod = "PUT";
        return [
            [self::LOGIN_REQUEST_METHOD, self::LOGIN_REQUEST_URI, self::EXPECTED_RETURN_VALUE],
            [self::LOGIN_REQUEST_METHOD, $wrongUri, null],
            [$wrongMethod, self::LOGIN_REQUEST_URI, null]
        ];
    }

    private static function initializeRequest($method, $uri) {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
    }
    
    private function getMockLogger($expected_level = null, $expected_message = null) {
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