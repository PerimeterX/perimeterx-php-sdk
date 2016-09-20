<?php
namespace Perimeterx;

class PerimeterxS2SValidator
{
    const RISK_API_ENDPOINT = '/api/v1/risk';
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
     * @param $pxCtx PerimeterxContext - perimeterx context
     * @param $pxConfig array - perimeterx configurations
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
        if ($this->pxConfig['module_mode'] == Perimeterx::$ACTIVE_MODE) {
            $risk_mode = 'active_blocking';
        } else {
            $risk_mode = 'monitor';
        }
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
                'http_version' => $this->pxCtx->getHttpVersion(),
                'risk_mode' => $risk_mode
            ]
        ];

        $vid = $this->pxCtx->getVid();
        if (isset($vid)) {
            $requestBody['vid'] = $vid;
        }

        $uuid = $this->pxCtx->getUuid();
        if (isset($uuid)) {
            $requestBody['uuid'] = $uuid;
        }

        if (in_array($this->pxCtx->getS2SCallReason(), ['cookie_expired', 'cookie_validation_failed'])) {
            if ($this->pxCtx->getDecodedCookie()) {
                $requestBody['additional']['px_cookie'] = $this->pxCtx->getDecodedCookie();
            }
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->pxConfig['auth_token'],
            'Content-Type' => 'application/json'
        ];

        if ($this->pxConfig['module_mode'] != Perimeterx::$ACTIVE_MODE and isset($this->pxConfig['custom_risk_handler'])) {
            $response = $this->pxConfig['custom_risk_handler']($this->pxConfig['perimeterx_server_host'] . self::RISK_API_ENDPOINT, 'POST', $requestBody, $headers);
        } else {
            $response = $this->httpClient->send(self::RISK_API_ENDPOINT, 'POST', $requestBody, $headers, $this->pxConfig['api_timeout'], $this->pxConfig['api_connect_timeout']);
        }
        return $response;
    }

    /**
     * @return array
     */
    private function formatHeaders()
    {
        $retval = [];
        foreach ($this->pxCtx->getHeaders() as $key => $value) {
            if (!in_array(strtolower($key), $this->pxConfig['sensitive_headers'])) {
                array_push($retval, ['name' => $key, 'value' => $value]);
            }
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
