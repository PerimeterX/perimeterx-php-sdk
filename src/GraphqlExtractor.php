<?php

namespace Perimeterx;

class GraphqlExtractor {
    /**
     * @return GraphqlFields
     */
    public static function ExtractGraphqlFields() {
        $postBody = json_decode(PerimeterxUtils::getPostRequestBody(), true);
        if (!isset($postBody)) {
            return null;
        }

        $query = GraphqlExtractor::extractGraphqlQuery($postBody);
        $queryArray = preg_split('/[^A-Za-z0-9_]/', $query);

        $operationType = GraphqlExtractor::extractOperationType($query, $queryArray);
        $operationName = GraphqlExtractor::extractOperationName($postBody, $queryArray);

        return new GraphqlFields($operationType, $operationName);
    }

    private static function extractGraphqlQuery(&$postRequestBody) {
        if (!array_key_exists('query', $postRequestBody)) {
            return '';
        }

        return $postRequestBody['query'];
    }

    private static function extractOperationType(&$query, &$queryArray) {
        $isGraphqlShorthand = $query[0] === '{';
        if ($isGraphqlShorthand) {
            return 'query';
        }

        $operationType = $queryArray[0];
        if (!in_array($operationType, ['query', 'mutation', 'subscription'])) {
            $operationType = 'query';
        }

        return $operationType;
    }

    private static function extractOperationName(&$postBody, &$queryArray) {
        if (array_key_exists('operationName', $postBody)) {
            return $postBody['operationName'];
        }

        if (!in_array($queryArray[0], ['query', 'mutation', 'subscription'])) {
            return $queryArray[0];
        }

        if ($queryArray[1]) {
            return $queryArray[1];
        }

        return null;
    }
}