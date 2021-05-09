<?php

use Perimeterx\PerimeterxUtils;

class PerimeterxUtilsDouble extends PerimeterxUtils {
    public static function setInputStreamName($inputStreamName) {
        static::$inputStreamName = $inputStreamName;
    }
}

class PerimeterxUtilsTest extends PHPUnit_Framework_TestCase {
    const TEMP_STREAM_NAME = "file://" . __DIR__ . "/tmp.txt";

    public function setUp() {
        PerimeterxUtilsDouble::setInputStreamName(self::TEMP_STREAM_NAME);
    }

    public function tearDown() {
        if (file_exists(self::TEMP_STREAM_NAME)) {
            unlink(self::TEMP_STREAM_NAME);
        }
    }

    /**
     * @dataProvider provideGetPostBodyData
     */
    public function testGetPostBody($readBodyCount) {
        $bodyString = json_encode([ "key1" => "value1", "key2" => "value2" ]);
        self::initializeRequest("POST", "/", $bodyString);
        foreach (range(1, $readBodyCount) as $i) {
            $receivedBodyString = PerimeterxUtils::getPostRequestBody();
        }
        $this->assertEquals($bodyString, $receivedBodyString);
    }

    public function provideGetPostBodyData() {
        return [ [1], [2], [100] ];
    }

    private static function initializeRequest($method = "GET", $uri = "/", $body = null, $headers = []) {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        if ($method === "POST") {
            self::initializePostRequest($uri, $body, $headers);
        }
    }

    private static function initializePostRequest($uri, $body, $headers) {
        if (!empty($body)) {
            $stream = fopen(self::TEMP_STREAM_NAME, 'w');
            fwrite($stream, $body);
            fclose($stream);
        }
    }

    /**
     * @dataProvider provideGetNestedObjectPropertyData
     */

    public function testGetNestedObjectProperty($object, $concatenatedPropertyString, $expectedValue) {
        $actualValue = PerimeterxUtils::getNestedArrayProperty($object, $concatenatedPropertyString);
        $this->assertEquals($expectedValue, $actualValue);
    }

    public function provideGetNestedObjectPropertyData() {
        $root = "root";
        $nested = "nested";
        $wrongKey = "wrongKey";
        $value = "value";
        $wrongValue = "wrongValue";
        return [
            [null, [$root], null],
            [$root, [$root], null],
            [[], [$root], null],
            [[$root => $value], [$root], $value],
            [[$root => $value], [$root, $nested], null],
            [[$root => [$nested => $value]], [$nested], null],
            [[$root => [$nested => $value]], [$root], [$nested => $value]],
            [[$root => [$nested => $value]], [$root, $nested], $value],
            [[$root => [$wrongKey => $value]], [$root, $nested], null],
            [[$root => [$nested => $value, $wrongKey => $wrongValue]], [$root, $nested], $value],
            [[$root => [$nested => [$nested => $value]]], [$root, $nested, $nested], $value]
        ];
    }
}

?>