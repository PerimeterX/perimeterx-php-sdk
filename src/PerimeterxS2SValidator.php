<?php
namespace Perimeterx;

class PerimeterxS2SValidator
{
    /**
     * @var string
     */
    private $pxAuthToken;

    /**
     * @var PerimeterxContext
     */
    private $pxCtx;

    /**
     * @var object - perimeterx configuration object
     */
    private $pxConfig;

    /**
     * @var PerimeterxHttpClient
     */
    private $httpClient;

    /**
     * @param PerimeterxContext - perimeterx context
     * @param array - perimeterx configurations
     */
    public function __construct($pxCtx, $pxConfig)
    {
        $this->pxConfig = $pxConfig;
        $this->pxAuthToken = $pxConfig['auth_token'];
        $this->httpClient = $pxConfig['http_client'];
        $this->pxCtx = $pxCtx;
    }

    private function sendRiskRequest()
    {
        $requestBody = [
            'request' => [
                'ip' => $this->pxCtx->getIp(),
                'headers' => $this->formatHeaders(),
                'uri' => $this->pxCtx->getUri(),
                'url' => $this->pxCtx->getFullUrl()
            ],
            'additional' => [
                's2s_call_reason' => $this->pxCtx->getS2SCallReason(),
                'module_version' => $this->pxConfig['sdk_name'],
                'http_method' => $this->pxCtx->getHttpMethod(),
                'http_version' => $this->pxCtx->getHttpVersion()
            ]
        ];
        
        $vid = $this->pxCtx->getVid();
        if (!isset($vid)) {
            $requestBody['vid'] = $vid;
        }
        $headers = [
            'Authorization' => 'Bearer ' . $this->pxConfig['auth_token'],
            'Content-Type' => 'application/json'
        ];

        if ($this->pxConfig['module_mode'] == Perimeterx::$MONITOR_MODE_ASYNC) {
            error_log('sending async');
            $this->httpClient->sendAsync(json_encode($requestBody), $this->pxConfig['auth_token']);
        } else {
            $response = $this->httpClient->send('/api/v1/risk', 'POST', $requestBody, $headers);
            return $response;
        }
    }

    /**
     * @return array
     */
    private function formatHeaders()
    {
        $retval = [];
        foreach ($this->pxCtx->getHeaders() as $key => $value) {
            array_push($retval, ['name' => $key, 'value' => $value]);
        }
        return $retval;

    }

    public function verify()
    {
        $response = json_decode($this->sendRiskRequest());
        if (isset($response, $response->scores, $response->scores->non_human)) {
            $score = $response->scores->non_human;
            $this->pxCtx->setScore($score);
            $this->pxCtx->setUuid($response->uuid);
            if ($score >= $this->pxConfig['blocking_score']) {
                $this->pxCtx->setBlockReason('s2s_high_score');
            }
        }

    }
}