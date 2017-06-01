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

    private function sendCaptchaRequest($vid, $uuid, $captcha)
    {
        $requestBody = [
            'request' => [
                'ip' => $this->pxCtx->getIp(),
                'headers' => $this->formatHeaders(),
                'uri' => $this->pxCtx->getUri()
            ],
            'pxCaptcha' => $captcha,
            'vid' => $vid,
            'uuid' => $uuid,
            'hostname' => $this->pxCtx->getHostname()
        ];
        $headers = [
            'Authorization' => 'Bearer ' . $this->pxConfig['auth_token'],
            'Content-Type' => 'application/json'
        ];
        $response = $this->httpClient->send('/api/v1/risk/captcha', 'POST', $requestBody, $headers, $this->pxConfig['api_timeout'], $this->pxConfig['api_connect_timeout']);
        return $response;
    }

    public function verify()
    {
        $startRiskRtt = $this->getTimeInMilliseconds();
        try {
            if (!isset($this->pxCaptcha)) {
                return false;
            }
            /* remove pxCaptcha cookie to prevert reuse */
            setcookie("_pxCaptcha", "", time() - 3600, "/");
            list($captcha, $vid, $uuid) = explode(':', $this->pxCaptcha, 3);
            if (!isset($captcha) || !isset($vid) || !isset($uuid)) {
                return false;
            }

            $this->pxCtx->setVid($vid);
            $this->pxCtx->setUuid($uuid);
            $response = json_decode($this->sendCaptchaRequest($vid, $uuid, $captcha));
            $this->pxCtx->setRiskRtt($this->getTimeInMilliseconds() - $startRiskRtt);
            if (isset($response->status) and $response->status == 0) {
                $this->pxCtx->setPassReason('captcha');
                return true;
            }
            return false;
        } catch (ConnectException $e){
            $this->pxCtx->setRiskRtt($this->getTimeInMilliseconds() - $startRiskRtt);

            // Catch timeout and pass request
            $this->pxCtx->setPassReason('captcha_timeout');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
