<?php

namespace Perimeterx;

abstract class PerimeterxRiskClient
{

    /**
     * @var PerimeterxContext
     */
    protected $pxCtx;

    /**
     * @var object - perimeterx configuration object
     */
    protected $pxConfig;

    /**
     * @var PerimeterxHttpClient
     */
    protected $httpClient;

    /**
     * @var PerimeterxUtils
     */
    protected $pxUtils;

    /**
     * @param $pxCtx PerimeterxContext - perimeterx context
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxCtx, $pxConfig)
    {
        $this->pxCtx = $pxCtx;
        $this->pxConfig = $pxConfig;
        $this->httpClient = $pxConfig['http_client'];
        $this->pxUtils = new PerimeterxUtils();
    }

    /**
     * @return array
     */
    protected function formatHeaders()
    {
        $retval = [];
        foreach ($this->pxCtx->getHeaders() as $key => $value) {
            if (!in_array(strtolower($key), $this->pxConfig['sensitive_headers'])) {
                array_push($retval, ['name' => $key, 'value' => $value]);
            }
        }

        return $retval;
    }

    /**
     * @return long current time in milliseconds
     */
    protected function getTimeInMilliseconds(){
        return round(microtime(true) * 1000);
    }
}
