<?php

namespace Perimeterx;

class TokenV1 extends PerimeterxToken
{
    /**
     * @param $pxCtx PerimeterxContext - perimeterx context
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxCtx, $pxConfig)
    {
        $this->pxPayload = $pxCtx->getPxCookie();
        $this->pxConfig = $pxConfig;
        $this->pxCtx = $pxCtx;
        $this->cookieSecret = $pxConfig['cookie_key'];
    }

    public function getScore()
    {
        return $this->getDecodedPayload()->s->b;
    }

    public function getHmac()
    {
        return $this->getDecodedPayload()->h;
    }

    protected function isCookieFormatValid($token) {
        return isset($token->t, $token->s, $token->s->b, $token->u, $token->v, $token->h);
    }

    public function getBlockAction() {
        // v1 cookie will return captcha action
        return 'c';
    }

    /**
     * Checks that the cookie is secure via HMAC
     *
     * @return bool
     */
    public function isSecure()
    {
        $base_hmac_str = $this->getTime() . $this->decodedPayload->s->a . $this->getScore() . $this->getUuid() . $this->getVid();

        /* hmac string with ip - for backward support */
        $hmac_str_withip = $base_hmac_str . $this->pxCtx->getIp();

        /* hmac string with no ip */
        $hmac_str_withoutip = $base_hmac_str;
        if ($this->isHmacValid($hmac_str_withoutip, $this->getHmac()) or $this->isHmacValid($hmac_str_withip, $this->getHmac())) {
            return true;
        }

        return false;
    }
}