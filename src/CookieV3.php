<?php

namespace Perimeterx;

class CookieV3 extends PerimeterxCookie
{

    /**
     * @param $pxCtx PerimeterxContext - perimeterx context
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxCtx, $pxConfig)
    {
        if ($pxConfig['encryption_enabled']) {
            $cookieValidPartsNumber = 4;
        } else {
            $cookieValidPartsNumber = 2;
        }

        $payloadParts = explode(":", $pxCtx->getPxCookie());
        if (count($payloadParts) < $cookieValidPartsNumber) {
            return null;
        }
        list($hash, $cookie) = explode(":", $pxCtx->getPxCookie(), 2);
        $this->pxPayload = $cookie;
        $this->cookieHash = $hash;
        $this->pxConfig = $pxConfig;
        $this->pxCtx = $pxCtx;
        $this->cookieSecret = $pxConfig['cookie_key'];
    }

    public function getScore()
    {
        return $this->getDecodedPayload()->s;
    }

    public function getHmac()
    {
        return $this->cookieHash;
    }

    protected function isCookieFormatValid($cookie) {
        return isset($cookie->t, $cookie->s, $cookie->u, $cookie->v, $cookie->a);
    }

    public function getBlockAction() {
        return $this->getDecodedPayload()->a;
    }

    /**
     * Checks that the cookie is secure via HMAC
     *
     * @return bool
     */
    public function isSecure()
    {
        $hmac_string = $this->pxPayload.$this->pxCtx->getUserAgent();

        return $this->isHmacValid($hmac_string, $this->getHmac());
    }
}
