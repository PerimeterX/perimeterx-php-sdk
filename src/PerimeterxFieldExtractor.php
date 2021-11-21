<?php

namespace Perimeterx;


class PerimeterxFieldExtractor {

    private static $NESTED_OBJECT_SEPARATOR = ".";
    private static $HASH_ALGO = "sha256";
    private $sentThrough;
    private $contentType;
    private $encoding;
    private $extractionFields;

    /**
     * @param string, @param string, @param string, @param array
     */
    public function __construct($sentThrough, $contentType, $encoding, $desiredFields) {
        $this->sentThrough = $sentThrough;
        $this->contentType = $contentType;
        $this->encoding = $encoding;
        $this->extractionFields = $desiredFields;
    }

    /**
     * @return array
     */
    public function extractFields() {
        $container = $this->getContainer();
        if (empty($container)) {
            return null;
        }
        $extractedCredentials = $this->getFieldsFrom($container, $this->extractionFields);
        if (empty($extractedCredentials)) {
            return null;
        }


        $keys = array_keys($extractedCredentials);
        return array_combine(
        $keys, 
        array_map(function($value, $key){ 
            if ($key == PerimeterxFieldExtractorManager::$USERNAME_FIELD) {
                $value = strtolower($value);
            }
            return hash(PerimeterxFieldExtractor::$HASH_ALGO, $value);    
        }, $extractedCredentials, $keys)
        );
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
        switch ($this->contentType) {
            case "json":
                return $this->getJsonBodyContainer();
            case "form":
            case "form-data":
                return $this->getFormBodyContainer();
            default:
                return null;
        }
    }

    private function getFormBodyContainer() {
        switch ($this->encoding) {
            case "url-encode":
            case "url-encoded":
                return $this->getUrlEncodedBodyContainer();
            case "clear-text": 
                return $this->getMultipartFormDataBodyContainer();
            default:
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
        foreach ($desiredFields as $originalRequestFieldName => $resultActivityFieldName) {
            $propertyArray = explode(PerimeterxFieldExtractor::$NESTED_OBJECT_SEPARATOR, $originalRequestFieldName);
            $value = PerimeterxUtils::getNestedArrayProperty($container, $propertyArray);
            if (!empty($value)) {
                $fields[$resultActivityFieldName] = $value;
            }
        }
        return $fields;
    }
}