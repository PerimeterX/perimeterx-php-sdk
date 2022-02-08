<?php

namespace Perimeterx\CredentialsIntelligence;

use Psr\Log\LoggerInterface;

class PerimeterxFieldExtractorManager {

    private static $USERNAME_FIELD = "user";
    private static $PASSWORD_FIELD = "pass";

    private $extractorMap;
    private $protocol;
    private $logger;

    /**
     * @param array $extractorMap
     * @param ICredentialsIntelligenceProtocol $protocol
     * @param Psr\Log\LoggerInterface $logger
     */
    public function __construct($extractorMap, $protocol, $logger) {
        $this->extractorMap = $extractorMap;
        $this->logger = $logger;
        $this->protocol = $protocol;
    }

    /**
     * @return LoginCredentialsFields
     */
    public function extractFields() {
        try {
            $uriWithoutQuery = strtok($_SERVER['REQUEST_URI'], "?");
            $extractorKey = self::generateMapKey($uriWithoutQuery, $_SERVER["REQUEST_METHOD"]);
            if (!array_key_exists($extractorKey, $this->extractorMap)) {
                return null;
            }
            $this->logger->debug("Attempting to extract credentials");
            $extractor = $this->extractorMap[$extractorKey];
            $fields = $extractor->extractFields();
            return $this->processFields($fields);
        } catch (\Exception $e) {
            $this->logger->error("Exception thrown while extracting credentials: " . $e->getMessage());
        } catch (\Error $e) {
            $this->logger->error("Error thrown while extracting credentials: " . $e->getMessage());
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
            if (!isset($extractConfig["path"], $extractConfig["method"])) {
                continue;
            }
            if (!isset($extractConfig["callback_name"]) &&
                !isset($extractConfig["sent_through"], $extractConfig["user_field"], $extractConfig["pass_field"])) {
                continue;
            }
            $extractorKey = self::generateMapKey($extractConfig["path"], $extractConfig["method"]);
            $extractorMap[$extractorKey] = new PerimeterxFieldExtractor(
                $extractConfig["sent_through"],
                array(
                    $extractConfig["user_field"] => PerimeterxFieldExtractorManager::$USERNAME_FIELD,
                    $extractConfig["pass_field"] => PerimeterxFieldExtractorManager::$PASSWORD_FIELD
                ), $extractConfig["callback_name"]);
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

    /**
     * @param array
     * @return LoginCredentialsFields
     */
    private function processFields($extractedFields) {
        if (!isset($extractedFields)) {
            $this->logger->debug("Failed extracting credentials");
            return null;
        }
        $user = $extractedFields[PerimeterxFieldExtractorManager::$USERNAME_FIELD];
        $pass = $extractedFields[PerimeterxFieldExtractorManager::$PASSWORD_FIELD];
        if (empty($user) && empty($pass)) {
            $this->logger->debug("Failed extracting credentials");
            return null;
        }

        $this->logger->debug("Successfully extracted credentials");
        return $this->protocol->ProcessCredentials($user, $pass);
    }


}