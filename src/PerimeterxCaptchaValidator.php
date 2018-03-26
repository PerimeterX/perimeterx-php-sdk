<?php

namespace Perimeterx;

use GuzzleHttp\Exception\ConnectException;

class PerimeterxCaptchaValidator extends PerimeterxRiskClient
{
    /**
     * @var string
     */
    private $pxCaptcha;

    /**
     * @param $pxCtx PerimeterxContext - perimeterx context
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxCtx, $pxConfig)
    {
        parent::__construct($pxCtx, $pxConfig);
        $this->pxCaptcha = $pxCtx->getPxCaptcha();
    }

    private function sendCaptchaRequest()
    {
        $requestBody = [
            'request' => [
                'ip' => $this->pxCtx->getIp(),
                'headers' => $this->formatHeaders(),
                'uri' => $this->pxCtx->getUri(),
                'captchaType' => $this->pxConfig['captcha_provider']
            ],
            'additional' => [
                'module_version' => $this->pxConfig['sdk_name']
            ],
            'pxCaptcha' => $this->pxCaptcha,
            'hostname' => $this->pxCtx->getHostname()
        ];
        $headers = [
            'Authorization' => 'Bearer ' . $this->pxConfig['auth_token'],
            'Content-Type' => 'application/json'
        ];
        $response = $this->httpClient->send('/api/v2/risk/captcha', 'POST', $requestBody, $headers, $this->pxConfig['api_timeout'], $this->pxConfig['api_connect_timeout']);
        return $response;
    }

    public function verify()
    {
        $startRiskRtt = $this->getTimeInMilliseconds();
        try {
            if (!isset($this->pxCaptcha)) {
                return false;
            }

            $this->pxConfig['logger']->debug('Captcha cookie found, evaluating');
            /* remove pxCaptcha cookie to prevert reuse */
            setcookie("_pxCaptcha", "", time() - 3600, "/");
            $response = json_decode($this->sendCaptchaRequest());
            $this->pxCtx->setRiskRtt($this->getTimeInMilliseconds() - $startRiskRtt);
            if (isset($response->status) and $response->status == 0) {
                $this->pxConfig['logger']->debug('Captcha API response validation status: passed');
                $this->pxCtx->setPassReason('captcha');
                return true;
            }

            $this->pxConfig['logger']->debug('Captcha API response validation status: failed');
            return false;
        } catch (ConnectException $e){
            $this->pxCtx->setRiskRtt($this->getTimeInMilliseconds() - $startRiskRtt);

            // Catch timeout and pass request
            $this->pxConfig['logger']->debug('Captcha response timeout - passing request');
            $this->pxCtx->setPassReason('captcha_timeout');
            return true;
        } catch (\Exception $e) {
            $this->pxConfig['logger']->error("Unexpected exception while evaluating Captcha cookie. {$e->getMessage()}");
            return false;
        }
    }
}
