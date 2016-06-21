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
                'uri' => $this->pxCtx->getUri()
            ],
            'additional' => [
                's2s_call_reason' => $this->pxCtx->getS2SCallReason()
            ]
        ];

        if (isset($_SERVER['SERVER_PROTOCOL'])) {
            $httpVer = explode("/", $_SERVER['SERVER_PROTOCOL']);
            if (isset($httpVer[1])) {
                $requestBody['additional']['http_version'] = $httpVer[1];
            }
        }
        
        $vid = $this->pxCtx->getVid();
        if (!isset($vid)) {
            $requestBody['vid'] = $vid;
        }
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->pxConfig['auth_token'],
            'Content-Type' => 'application/json'
        ];
        $response = $this->httpClient->send('/api/v1/risk', 'POST', $requestBody, $headers);
        return $response;
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
        if (isset($response->scores, $response->scores->non_human)) {
            $score = $response->scores->non_human;
            $this->pxCtx->setScore($score);
            $this->pxCtx->setUuid($response->uuid);
            if ($score >= $this->pxConfig['blocking_score']) {
                $this->pxCtx->setBlockReason('s2s_high_score');
            }
        }

    }
}