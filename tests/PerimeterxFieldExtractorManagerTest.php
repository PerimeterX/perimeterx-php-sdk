<?php

use Psr\Log\AbstractLogger;
use Perimeterx\CredentialsIntelligence\PerimeterxFieldExtractor;
use Perimeterx\CredentialsIntelligence\PerimeterxFieldExtractorManager;
use Perimeterx\CredentialsIntelligence\Protocol\V1CredentialsIntelligenceProtocol;

class PerimeterxFieldExtractorManagerTest extends PHPUnit_Framework_TestCase
{
    const LOGIN_REQUEST_URI = "/login";
    const LOGIN_REQUEST_METHOD = "POST";

    const USER_FIELD = "user";
    const PASS_FIELD = "pass";

    const USER_VALUE = "pxUser";
    const PASS_VALUE = "1234";

    const HASHED_USER_VALUE = "9620f4cab3b3a50b9cbcb9a8d01328874ec33eb6882ae31c022f6986fc516851";
    const HASHED_PASS_VALUE = "03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4";

    const CI_VERSION_FIELD = "ci_version";

    const EXPECTED_RETURN_VALUE = [
        self::USER_FIELD => self::HASHED_USER_VALUE,
        self::PASS_FIELD => self::HASHED_PASS_VALUE,
        self::CI_VERSION_FIELD => 'v1'
    ];
    
    /**
     * @var PerimeterxFieldExtractorManager
     */
    private $fieldExtractorManager;

    public function setUp() {
        $mapKey = PerimeterxFieldExtractorManager::generateMapKey(self::LOGIN_REQUEST_URI, self::LOGIN_REQUEST_METHOD);
        $mockExtractor = $this->createMock(PerimeterxFieldExtractor::class);
        $mockExtractor
            ->method('extractFields')
            ->willReturn([
                self::USER_FIELD => self::USER_VALUE,
                self::PASS_FIELD => self::PASS_VALUE
            ]);
        $extractionMap = [ $mapKey => $mockExtractor ];
        $protocol = new V1CredentialsIntelligenceProtocol();
        $this->fieldExtractorManager = new PerimeterxFieldExtractorManager($extractionMap, $protocol, $this->getMockLogger());
    }

    /**
     * @dataProvider provideExtractCredentialsData
     */
    public function testExtractCredentials($method, $uri, $expectedReturnValue) {
        self::initializeRequest($method, $uri);
        $actualReturnValue = $this->fieldExtractorManager->extractFields();
        if (is_null($expectedReturnValue)) {
            $this->assertNull($actualReturnValue);
        } else {
            $this->assertEquals($expectedReturnValue[self::USER_FIELD], $actualReturnValue->getUser());
            $this->assertEquals($expectedReturnValue[self::PASS_FIELD], $actualReturnValue->getPass());
            $this->assertEquals($expectedReturnValue[self::CI_VERSION_FIELD], $actualReturnValue->getCIVersion());
        }
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