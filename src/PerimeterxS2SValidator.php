<?php

namespace Perimeterx;

class PerimeterxS2SValidator extends PerimeterxRiskClient
{
    const RISK_API_ENDPOINT = '/api/v2/risk';

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

    public function verify()
    {
        $response = json_decode($this->sendRiskRequest());
        $this->pxCtx->setIsMadeS2SRiskApiCall(true);
        if (isset($response, $response->score, $response->action)) {
            $score = $response->score;
            $this->pxCtx->setScore($score);
            $this->pxCtx->setUuid($response->uuid);
            $this->pxCtx->setBlockAction($response->action);
            if ($response->action == 'j' && $response['data']['body'] && $response['data']['body']) {
                $this->pxCtx->setBlockActionData($response['data']['body']);
                $this->pxCtx->setBlockReason('challenge');
            } elseif ($score >= $this->pxConfig['blocking_score']) {
                $this->pxCtx->setBlockReason('s2s_high_score');
            }
        }
        if (isset($response, $response->error_msg)) {
            $this->pxCtx->setS2SHttpErrorMsg($response->error_msg);
        }
    }
}
