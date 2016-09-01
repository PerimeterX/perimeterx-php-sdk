<?php
namespace Perimeterx;

class PerimeterxCaptchaValidator
{
    /**
     * @var string
     */
    private $pxCaptcha;

    /**
     * @var PerimeterxContext
     */
    private $pxCtx;

    /**
     * @var object - perimeterx configuration object
     */
    private $pxConfig;

    /**
     * @var string
     */
    private $pxAuthToken;


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
        $this->pxCaptcha = $pxCtx->getPxCaptcha();
        $this->pxConfig = $pxConfig;
        $this->pxCtx = $pxCtx;
        $this->pxAuthToken = $pxConfig['auth_token'];
        $this->httpClient = $pxConfig['http_client'];
    }

    private function sendCaptchaRequest($vid, $captcha)
    {
        $requestBody = [
            'request' => [
                'ip' => $this->pxCtx->getIp(),
                'headers' => $this->formatHeaders(),
                'uri' => $this->pxCtx->getUri()
            ],
            'pxCaptcha' => $captcha,
            'vid' => $vid,
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
        try {
            if (!isset($this->pxCaptcha)) {
                return false;
            }
            /* remove pxCaptcha cookie to prevert reuse */
            setcookie("_pxCaptcha", "", time() - 3600);
            list($captcha, $vid) = explode(':', $this->pxCaptcha, 2);
            if (!isset($captcha)) {
                return false;
            }

            $this->pxCtx->setVid($vid);
            $response = json_decode($this->sendCaptchaRequest($vid, $captcha));
            if (isset($response->status) and $response->status == 0) {
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
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
}
