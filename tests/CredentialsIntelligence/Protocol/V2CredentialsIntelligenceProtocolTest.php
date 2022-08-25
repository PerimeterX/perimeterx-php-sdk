<?php

use Perimeterx\CredentialsIntelligence\Protocol\V2CredentialsIntelligenceProtocol;
use Perimeterx\CredentialsIntelligence\LoginCredentialsFields;
use Perimeterx\CredentialsIntelligence\Protocol\CIVersion;

class V2CredentialsIntelligenceProtocolTests extends PHPUnit_Framework_TestCase {

    /**
     * @dataProvider provideCredentialsAndExpectedHashes
     */
    public function testV2CIProtocolReturnsCorrectHashes($username, $password, $userHash, $passHash) {
        $protocol = new V2CredentialsIntelligenceProtocol();
        $loginCredentialsFields = $protocol->ProcessCredentials($username, $password);

        $this->assertEquals($userHash, $loginCredentialsFields->getUser());
        $this->assertEquals($passHash, $loginCredentialsFields->getPass());
        $this->assertEquals(CIVersion::V2, $loginCredentialsFields->getCIVersion());
        $this->assertEquals($username, $loginCredentialsFields->getRawUsername());
        $this->assertEmpty($loginCredentialsFields->getSsoStep());
    }

    public function provideCredentialsAndExpectedHashes() {
        return [
            ["pxUser", "1234", "9620f4cab3b3a50b9cbcb9a8d01328874ec33eb6882ae31c022f6986fc516851", "c958c33151f273c620ec658ac4de9abd33ad7627df5d8c468224b0bae7173eb4"],
            ["Perimeter.X+001@perimeterx.com", "1234", "53225d1fa939355031fa2208a44ada1bf9953f0d0daf894baa984a4310df6b48", "ddf40c1584801828bda92cc493373d045de593f73c4fb40aab5de7f19aa8df94"],
            ["Perimeter.X+001@gmail.com", "1234", "2bd9bd06f3440c682044a3f1b1fa7a97bd8b568a6e9e7d2cb0c6e858d9c78069", "5246d99e5d2506d70db44e8216aecb7be42bf5bf7bc1766a680cbdad2ce046ab"]
        ];
    }
}