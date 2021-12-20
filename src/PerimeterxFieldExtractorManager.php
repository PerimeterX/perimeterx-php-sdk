<?php

namespace Perimeterx;

use Psr\Log\LoggerInterface;

class PerimeterxFieldExtractorManager {

    private static $USERNAME_FIELD = "user";
    private static $PASSWORD_FIELD = "pass";

    private $extractorMap;
    private $logger;

    public function __construct(array $extractorMap, $logger) {
        $this->extractorMap = $extractorMap;
        $this->logger = $logger;
    }

    /**
     * @return array | null
     */
    public function extractFields() {
        try {
            $uriWithoutQuery = strtok($_SERVER['REQUEST_URI'], "?");
            $extractorKey = self::generateMapKey($uriWithoutQuery, $_SERVER["REQUEST_METHOD"]);
            $extractor = $this->extractorMap[$extractorKey];
            return isset($extractor) ? $extractor->extractFields() : null;
        } catch (\Exception $e) {
            $this->logger->error("Exception thrown while extracting fields: " . $e->getMessage());
        } catch (\Error $e) {
            $this->logger->error("Error thrown while extracting fields: " . $e->getMessage());
        }
        return null;
    }

    /**
     * @param array of arrays
     * @return array
     */

    public static function createExtractorMap(array &$extractConfigs) {
        $extractorMap = array();
        foreach ($extractConfigs as $extractConfig) {
            if (!isset($extractConfig["path"], $extractConfig["method"], $extractConfig["sentThrough"], $extractConfig["contentType"], 
                       $extractConfig["encoding"], $extractConfig["passField"], $extractConfig["userField"])) {
                continue;
            }
            $extractorKey = self::generateMapKey($extractConfig["path"], $extractConfig["method"]);
            $extractorMap[$extractorKey] = new PerimeterxFieldExtractor(
                $extractConfig["sentThrough"], $extractConfig["contentType"], $extractConfig["encoding"],
                array(
                    $extractConfig["userField"] => PerimeterxFieldExtractorManager::$USERNAME_FIELD,
                    $extractConfig["passField"] => PerimeterxFieldExtractorManager::$PASSWORD_FIELD
                ), $extractConfig["callbackName"]);
        }
        return $extractorMap;
    }

    /**
     * @param string, @param string
     * @return string
     */
    public static function generateMapKey($requestPath, $requestMethod) {
        return $requestPath . ":" . strtoupper($requestMethod);
    }
}