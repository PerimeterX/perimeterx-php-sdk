<?php

namespace Perimeterx\CredentialsIntelligence;
use Perimeterx\PerimeterxUtils;

class PerimeterxFieldExtractor {
    private static $NESTED_OBJECT_SEPARATOR = ".";
    private $sentThrough;
    private $extractionFields;
    private $callbackName;

    /**
     * @param string $sentThrough
     * @param array $desiredFields
     * @param string $callbackName
     */
    public function __construct($sentThrough, $desiredFields, $callbackName) {
        $this->sentThrough = $sentThrough;
        $this->extractionFields = $desiredFields;
        $this->callbackName = $callbackName;
    }

    /**
     * @return array
     */
    public function extractFields() {
        $extractedCredentials = $this->getCredentials();
        if (empty($extractedCredentials)) {
            return null;
        }

        return $extractedCredentials;
    }

    private function getCredentials() {
        if (!empty($this->callbackName) && is_callable($this->callbackName)) {
            return call_user_func($this->callbackName);
        }
        $container = $this->getContainer();
        if (empty($container)) {
            return null;
        }
        return $this->getFieldsFrom($container, $this->extractionFields);
    }

    private function getContainer() {
        switch ($this->sentThrough) {
            case "body":
                return $this->getBodyContainer();
            case "header":
                return $this->getHeaderContainer();
            case "query-param":
                return $this->getQueryContainer();
            default:
                return null;
        }
    }

    private function getBodyContainer() {
        $contentType = $_SERVER['Content-Type'];
        if (!isset($contentType)) {
            $contentType = getallheaders()['Content-Type'];
        }
        if (strpos($contentType, "json") !== false) {
            return $this->getJsonBodyContainer();
        } else if (strpos($contentType, "x-www-form-urlencode") !== false) {
            return $this->getUrlEncodedBodyContainer();
        } else if (strpos($contentType, "multipart/form-data") !== false) {
            return $this->getMultipartFormDataBodyContainer();
        } else {
            return null;
        }
    }

    private function getHeaderContainer() {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            $headers[$name] = utf8_encode($value);
        }
        return $headers;
    }

    private function getQueryContainer() {
        $queryString = $_SERVER['QUERY_STRING'];
        parse_str($queryString, $queryParams);
        return $queryParams;
    }

    private function getUrlEncodedBodyContainer() {
        $body = PerimeterxUtils::getPostRequestBody();
        parse_str($body, $form);
        return $form;
    }

    private function getMultipartFormDataBodyContainer() {
        return $_REQUEST;
    }

    private function getJsonBodyContainer() {
        $jsonBody = PerimeterxUtils::getPostRequestBody();
        if (empty($jsonBody)) {
            return null;
        }
        $jsonArray = json_decode($jsonBody, true);
        if (empty($jsonArray)) {
            return null;
        }
        return $jsonArray;
    }

    private function getFieldsFrom(&$container, &$desiredFields) {
        if (empty($container)) {
            return null;
        }

        $fields = [];
        foreach ($desiredFields as $originalFieldName => $desiredFieldName) {
            $value = $container[str_replace(".", "_", $originalFieldName)];

            if (empty($value)) {
                $propertyArray = explode(PerimeterxFieldExtractor::$NESTED_OBJECT_SEPARATOR, $originalFieldName);
                $value = PerimeterxUtils::getNestedArrayProperty($container, $propertyArray);
            }

            if (!empty($value)) {
                $fields[$desiredFieldName] = $value;
            }
        }
        return $fields;
    }
}