<?php

namespace Perimeterx;

class GraphqlFields {
    private $operationType;
    private $operationName;

    /**
     * @param string $operationType
     * @param string $operationName
     */
    public function __construct($operationType, $operationName) {
        $this->operationType = $operationType;
        $this->operationName = $operationName;
    }

    /**
     * @return string
     */
    public function getOperationType() {
        return $this->operationType;
    }

    /**
     * @return string
     */
    public function getOperationName() {
        return $this->operationName;
    }
}