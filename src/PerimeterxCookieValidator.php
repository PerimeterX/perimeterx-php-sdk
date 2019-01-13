<?php

namespace Perimeterx;

class PerimeterxCookieValidator
{
    /**
     * @var string
     */
    private $pxCookie;

    /**
     * @var PerimeterxContext
     */
    private $pxCtx;

    /**
     * @var object - perimeterx configuration object
     */
    private $pxConfig;

    /**
     * @param $pxCtx PerimeterxContext - perimeterx context
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxCtx, $pxConfig)
    {
        $this->pxCookie = $pxCtx->getPxCookie();
        $this->pxConfig = $pxConfig;
        $this->pxCtx = $pxCtx;
    }

    /**
     * @return bool - main verification function, decrypt/decode cookie if exists and verify its content
     */
    public function verify()
    {
        try {
            if (!isset($this->pxCookie)) {
                $this->pxConfig['logger']->debug('Cookie is missing');
                $call_reason = ($this->pxCtx->getPxhdCookie() != null) ? "no_cookie_w_vid" : "no_cookie";
                $this->pxCtx->setS2SCallReason($call_reason);
                return false;
            }

            // Mobile SDK traffic
            if (isset($this->pxCookie) && $this->pxCtx->getCookieOrigin() == "header") {
                $cookieValue = (string)$this->pxCookie;
                if (preg_match('/^\d+$/', $cookieValue)) {
                    $this->pxCtx->setS2SCallReason('mobile_error_' . $cookieValue);
                    $this->pxConfig['logger']->debug("Mobile special token: {$this->pxCtx->getPxCookie()}");
                    if ($this->pxCtx->getOriginalToken()) {
                        $validator = new PerimeterxOriginalTokenValidator($this->pxCtx, $this->pxConfig);
                        $validator->verify();
                    }
                    return false;
                }
            }

            $cookie = PerimeterxPayload::pxPayloadFactory($this->pxCtx, $this->pxConfig);
            $this->pxConfig['logger']->debug("Cookie {$this->pxCtx->getCookieVersion()} found, Evaluating");

            if (!$cookie->deserialize()) {
                $this->pxConfig['logger']->debug("Cookie decryption failed, value: {$this->pxCtx->getPxCookie()}");
                $this->pxCtx->setS2SCallReason('cookie_decryption_failed');
                return false;
            }

            $this->pxCtx->setDecodedCookie($cookie->getDecodedPayload());
            $this->pxCtx->setScore($cookie->getScore());
            $this->pxCtx->setUuid($cookie->getUuid());
            $this->pxCtx->setVid($cookie->getVid());
            $this->pxCtx->setBlockAction($cookie->getBlockAction());
            $this->pxCtx->setResponseBlockAction($cookie->getBlockAction());
            $this->pxCtx->setCookieHmac($cookie->getHmac());

            if ($cookie->isExpired()) {
                $payloadString = json_encode($cookie->getDecodedPayload());
                $cookieAge = $this->getTimeInMilliseconds() - $cookie->getTime();
                $this->pxConfig['logger']->debug("Cookie TTL is expired, value: $payloadString, age: $cookieAge");
                $this->pxCtx->setS2SCallReason('cookie_expired');
                return false;
            }

            if ($cookie->isHighScore()) {
                $this->pxConfig['logger']->debug("Cookie evaluation ended successfully, risk score: {$cookie->getScore()}");
                $this->pxCtx->setBlockReason('cookie_high_score');
                return true;
            }

            if (!$cookie->isSecure()) {
                $payloadString = json_encode($cookie->getDecodedPayload());
                $this->pxConfig['logger']->debug("Cookie HMAC validation failed, value: $payloadString, user-agent: {$this->pxCtx->getUserAgent()}");
                $this->pxCtx->setS2SCallReason('cookie_validation_failed');
                return false;
            }

            // Case we have a sensitive route
            if ($this->pxCtx->isSensitiveRoute()) {
                $this->pxConfig['logger']->debug("Sensitive route match, sending Risk API. path: {$this->pxCtx->getUri()}");
                $this->pxCtx->setS2SCallReason('sensitive_route');
                return false;
            }

            $this->pxCtx->setPassReason('cookie');
            $this->pxConfig['logger']->debug("Cookie evaluation ended successfully, risk score: {$cookie->getScore()}");

            return true;
        } catch (\Exception $e) {
            $this->pxConfig['logger']->error("Unexpected exception while evaluating Risk cookie. {$e->getMessage()}");
            $this->pxCtx->setS2SCallReason('cookie_decryption_failed');
            return false;
        }
    }
    private function getTimeInMilliseconds(){
        return round(microtime(true) * 1000);
    }
}
