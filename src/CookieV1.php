<?php

namespace Perimeterx;

class CookieV1 extends PerimeterxCookie
{

    /**
     * @param $pxCtx PerimeterxContext - perimeterx context
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxCtx, $pxConfig)
    {
        $this->pxCookie = $pxCtx->getPxCookie();
        $this->pxConfig = $pxConfig;
        $this->pxCtx = $pxCtx;
        $this->cookieSecret = $pxConfig['cookie_key'];
    }

    public function getScore()
    {
        return $this->getDecodedCookie()->s->b;
    }

    protected function getHmac()
    {
        return $this->getDecodedCookie()->h;
    }

    protected function isCookieFormatValid($cookie) {
        return isset($cookie->t, $cookie->s, $cookie->s->b, $cookie->u, $cookie->v, $cookie->h);
    }

    public function getBlockAction() {
        // v1 cookie will return captcha action
        return 'captcha';
    }

    /**
     * Checks that the cookie is secure via HMAC
     *
     * @return bool
     */
    public function isSecure()
    {
        $base_hmac_str = $this->getTime() . $this->decodedCookie->s->a . $this->getScore() . $this->getUuid() . $this->getVid();

        /* hmac string with ip - for backward support */
        $hmac_str_withip = $base_hmac_str . $this->pxCtx->getIp() . $this->pxCtx->getUserAgent();

        /* hmac string with no ip */
        $hmac_str_withoutip = $base_hmac_str . $this->pxCtx->getUserAgent();

        if ($this->isHmacValid($hmac_str_withoutip, $this->getHmac()) or $this->isHmacValid($hmac_str_withip, $this->getHmac())) {
            return true;
        }

        return false;
    }

}
