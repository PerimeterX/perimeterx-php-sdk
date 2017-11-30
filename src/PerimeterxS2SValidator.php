<?php

namespace Perimeterx;

use GuzzleHttp\Exception\ConnectException;

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
                'risk_mode' => $risk_mode,
                'px_cookie_hmac' => $this->pxCtx->getCookieHmac(),
                'cookie_origin' => $this->pxCtx->getCookieOrigin()
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

        if ( $this->pxCtx->getS2SCallReason() ==  'cookie_decryption_failed') {
          $this->pxConfig['logger']->info('attaching px_orig_cookie to request');
          $requestBody['additional']['px_cookie_orig'] = $this->pxCtx->getPxCookie();
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
        $startRiskRtt = $this->getTimeInMilliseconds();
        try {
            if ($this->pxConfig['module_mode'] != Perimeterx::$ACTIVE_MODE and isset($this->pxConfig['custom_risk_handler'])) {
                $response = $this->pxConfig['custom_risk_handler']($this->pxConfig['perimeterx_server_host'] . self::RISK_API_ENDPOINT, 'POST', $requestBody, $headers);
            } else {
                $response = $this->httpClient->send(self::RISK_API_ENDPOINT, 'POST', $requestBody, $headers, $this->pxConfig['api_timeout'], $this->pxConfig['api_connect_timeout']);
            }
            $this->pxCtx->setRiskRtt($this->getTimeInMilliseconds() - $startRiskRtt);
            return $response;
        } catch ( ConnectException $e) {
            $this->pxCtx->setRiskRtt($this->getTimeInMilliseconds() - $startRiskRtt);
            $this->pxCtx->setPassReason('s2s_timeout');
            $this->pxConfig['logger']->debug("Risk API timed out, round_trip_time: {$this->pxCtx->getRiskRtt()}");
            return json_encode(['error_msg' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->pxConfig['logger']->error("Unexpected exception while evaluating Risk cookie. {$e->getMessage()}");
            return false;
        }
    }

    public function verify()
    {
        $this->pxConfig['logger']->debug("Evaluating Risk API request, call reason: {$this->pxCtx->getS2SCallReason()}");
        $response = json_decode($this->sendRiskRequest());
        $this->pxCtx->setIsMadeS2SRiskApiCall(true);
        if (isset($response, $response->score, $response->action)) {
            $this->pxConfig['logger']->debug("Risk API response returned successfully, risk score: {$response->score}, round_trip_time: {$this->pxCtx->getRiskRtt()}");
            $score = $response->score;
            $this->pxCtx->setScore($score);
            $this->pxCtx->setUuid($response->uuid);
            $this->pxCtx->setBlockAction($response->action);
            if ($response->action == 'j' && $response->action_data && $response->action_data->body) {
                $this->pxCtx->setBlockActionData($response->action_data->body);
                $this->pxCtx->setBlockReason('challenge');
            } elseif ($score >= $this->pxConfig['blocking_score']) {
                $this->pxConfig['logger']->debug("Risk score is higher or equal to blocking score. score: $score blocking score: {$this->pxConfig['blocking_score']}");
                $this->pxCtx->setBlockReason('s2s_high_score');
            }else{
                $this->pxConfig['logger']->debug("Risk score is lower than blocking score. score: $score blocking score: {$this->pxConfig['blocking_score']}");
                $this->pxCtx->setPassReason('s2s');
            }
        }
        if (isset($response, $response->error_msg)) {
            $this->pxCtx->setS2SHttpErrorMsg($response->error_msg);
        }
    }
}
