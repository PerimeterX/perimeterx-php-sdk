<?php

namespace Perimeterx;

use GuzzleHttp\Exception\ConnectException;

class PerimeterxS2SValidator extends PerimeterxRiskClient
{
    const RISK_API_ENDPOINT = '/api/v3/risk';

    public function verify() {
        $this->pxConfig['logger']->debug("Evaluating Risk API request, call reason: {$this->pxCtx->getS2SCallReason()}");
        $response = json_decode($this->sendRiskRequest());
        $this->pxCtx->setIsMadeS2SRiskApiCall(true);

        if (isset($response, $response->score, $response->action)) {
            $this->handle_valid_risk_response($response);
        } else {
            $this->handle_s2s_error($response);
        }
    }

    private function sendRiskRequest() {
        $headers = $this->prepareRiskRequestHeaders();
        $requestBody = $this->prepareRiskRequestBody();
        $startRiskRtt = $this->getTimeInMilliseconds();
        try {
            if ($this->pxConfig['module_mode'] != Perimeterx::$ACTIVE_MODE and isset($this->pxConfig['custom_risk_handler'])) {
                $response = $this->pxConfig['custom_risk_handler']($this->pxConfig['perimeterx_server_host'] . self::RISK_API_ENDPOINT, 'POST', $requestBody, $headers);
            } else {
                $response = $this->httpClient->send(self::RISK_API_ENDPOINT, 'POST', $requestBody, $headers, $this->pxConfig['api_timeout'], $this->pxConfig['api_connect_timeout'], $this->pxCtx);
            }
            $this->pxCtx->setRiskRtt($this->getTimeInMilliseconds() - $startRiskRtt);
            return $response;
        } catch ( ConnectException $e) {
            $this->pxCtx->setRiskRtt($this->getTimeInMilliseconds() - $startRiskRtt);
            $this->pxCtx->setPassReason('s2s_timeout');
            $this->pxConfig['logger']->debug("Risk API timed out, round_trip_time: {$this->pxCtx->getRiskRtt()}");
            return json_encode(['error_msg' => $e->getMessage(), 'error_code' => $e->getCode()]);
        } catch (\Exception $e) {
            $this->pxCtx->setRiskRtt($this->getTimeInMilliseconds() - $startRiskRtt);
            return json_encode(['error_msg' => $e->getMessage(), 'error_code' => $e->getCode()]);
        }
    }

    private function prepareRiskRequestHeaders() {
        return [
            'Authorization' => 'Bearer ' . $this->pxConfig['auth_token'],
            'Content-Type' => 'application/json'
        ];
    }

    private function prepareRiskRequestBody() {
        if ($this->pxConfig['module_mode'] == Perimeterx::$ACTIVE_MODE) {
            $risk_mode = 'active_blocking';
        } else {
            $risk_mode = 'monitor';
        }

        $vid_source = "none";

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
                'cookie_origin' => $this->pxCtx->getCookieOrigin(),
                'request_cookie_names' => $this->pxCtx->getCookieNames()
            ]
        ];

        $pxvid = $this->pxCtx->getPxVidCookie();
        $vid = $this->pxCtx->getVid();
        if (isset($vid)) {
            $vid_source = "risk_cookie";
            $requestBody['vid'] = $vid;
        } else if (isset($pxvid) && preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/', $pxvid)) {
            $vid_source = "vid_cookie";
            $requestBody['vid'] = $pxvid;
        }

        $requestBody["additional"]["enforcer_vid_source"] = $vid_source;

        $uuid = $this->pxCtx->getUuid();
        if (isset($uuid)) {
            $requestBody['uuid'] = $uuid;
        }

        $pxhd = $this->pxCtx->getPxhdCookie();
        if (isset($pxhd)) {
            $requestBody['pxhd'] = $pxhd;
        }

        if ($this->pxCtx->getS2SCallReason() ==  'cookie_decryption_failed') {
          $this->pxConfig['logger']->info('attaching px_orig_cookie to request');
          $requestBody['additional']['px_cookie_orig'] = $this->pxCtx->getPxCookie();
        }

        if (in_array($this->pxCtx->getS2SCallReason(), ['cookie_expired', 'cookie_validation_failed'])) {
            if ($this->pxCtx->getDecodedCookie()) {
                $requestBody['additional']['px_cookie'] = $this->pxCtx->getDecodedCookie();
            }
        }

        $original_uuid = $this->pxCtx->getOriginalTokenUuid();
        if (isset($original_uuid)) {
            $requestBody['additional']['original_uuid'] = $original_uuid;
        }

        $original_token_error = $this->pxCtx->getOriginalTokenError();
        if (isset($original_token_error)) {
            $requestBody['additional']['original_token_error'] = $original_token_error;
        }

        $original_token = $this->pxCtx->getOriginalToken();
        if (isset($original_token)) {
            $requestBody['additional']['original_token'] = $original_token;
        }

        $decoded_original_token = $this->pxCtx->getDecodedOriginalToken();
        if (isset($decoded_original_token)) {
            $requestBody['additional']['px_decoded_original_token'] = $decoded_original_token;
        }

        if (isset($this->pxConfig['enrich_custom_params'])) {
            $this->pxUtils->handleCustomParams($this->pxConfig, $requestBody['additional']);
        }
        return $requestBody;
    }

    private function handle_valid_risk_response($response) 
    {
        $this->pxConfig['logger']->debug("Risk API response returned successfully, risk score: {$response->score}, round_trip_time: {$this->pxCtx->getRiskRtt()}");
        $score = $response->score;
        $this->pxCtx->setScore($score);
        $this->pxCtx->setUuid($response->uuid);
        $this->pxCtx->setBlockAction($response->action);
        $this->pxCtx->setResponseBlockAction($response->action);
        if (isset($response->pxhd)) {
            setrawcookie("_pxhd", $response->pxhd, time() + 31557600, "/"); // expires in 1 year
        }
        if(isset($response->data_enrichment)) {
            $this->pxCtx->setDataEnrichmentVerified(true);
            $this->pxCtx->setDataEnrichment($response->data_enrichment);
        }

        if ($response->action == 'j' && $response->action_data && $response->action_data->body) {
            $this->pxCtx->setBlockActionData($response->action_data->body);
            $this->pxCtx->setBlockReason('challenge');
        } elseif ($response->action == 'r') {
            $this->pxCtx->setBlockReason('exceeded_rate_limit');
        } elseif ($score >= $this->pxConfig['blocking_score']) {
            $this->pxConfig['logger']->debug("Risk score is higher or equal to blocking score. score: $score blocking score: {$this->pxConfig['blocking_score']}");
            $this->pxCtx->setBlockReason('s2s_high_score');
        } else {
            $this->pxConfig['logger']->debug("Risk score is lower than blocking score. score: $score blocking score: {$this->pxConfig['blocking_score']}");
            $this->pxCtx->setPassReason('s2s');
        }
    }

    private function handle_s2s_error($response)
    {
        if (!empty($this->pxCtx->getPassReason())) {
            return;
        }

        $response_str = json_encode($response);
        $s2s_error_reason = "unknown_error";
        $s2s_error_message = "Response: \"$response_str\"";

        $http_status = $this->pxCtx->getS2SErrorHttpStatus();
        if (isset($http_status)) {
            if ($http_status == 200) {
                $s2s_error_reason = "invalid_response";
            } elseif (400 <= $http_status && $http_status < 500) {
                $s2s_error_reason = "bad_request";
            } elseif (500 <= $http_status && $http_status < 600) {
                $s2s_error_reason = "server_error";
            }
        }

        if (isset($response, $response->status) && $response->status !== 0) {
            $s2s_error_reason = "request_failed_on_server";
        }

        $this->pxConfig['logger']->error("s2s_error: $s2s_error_reason - $s2s_error_message");
        $this->pxCtx->setS2SError($s2s_error_reason, $s2s_error_message);
    }
}
