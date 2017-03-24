<?php

namespace Perimeterx;

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

    private function sendCaptchaRequest($captcha)
    {
        $requestBody = [
            'request' => [
                'ip' => $this->pxCtx->getIp(),
                'headers' => $this->formatHeaders(),
                'url' => $this->pxCtx->getFullUrl()
            ],
            'pxCaptcha' => $captcha,
            'hostname' => $this->pxCtx->getHostname()
        ];
        $headers = [
            'Authorization' => 'Bearer ' . $this->pxConfig['auth_token'],
            'Content-Type' => 'application/json'
        ];
        $response = $this->httpClient->send('/api/v2/risk/captcha/funcaptcha', 'POST', $requestBody, $headers, $this->pxConfig['api_timeout'], $this->pxConfig['api_connect_timeout']);
        return $response;
    }

    public function verify()
    {
        try {
            if (!isset($this->pxCaptcha)) {
                return false;
            }
            /* remove pxCaptcha cookie to prevert reuse */
            setcookie("_pxCaptcha", "", time() - 3600, "/");
            $response = json_decode($this->sendCaptchaRequest($this->pxCaptcha));
            if (isset($response->status) and $response->status == 0) {
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
