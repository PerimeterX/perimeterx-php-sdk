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
        list($hash, $cookie) = explode(":", $pxCtx->getPxCookie());
        $this->pxCookie = $cookie;
        $this->cookieHash = $has;
        $this->pxConfig = $pxConfig;
        $this->pxCtx = $pxCtx;
        $this->cookieSecret = $pxConfig['cookie_key'];
    }

    protected function getCookie() {
        return $this->pxCookie;
    }

    public function getScore()
    {
        return $this->getDecodedCookie()->s;
    }

    protected function getHmac()
    {
        return $this->cookieHash;
    }

    protected function isCookieFormatValid($cookie) {
        return isset($cookie->t, $cookie->s, $cookie->u, $cookie->v, $cookie->a);
    }

    public function getBlockAction() {
        return $this->getDecodedCookie()->a == 'c' ? 'captcha' : 'block';
    }

    /**
     * Checks that the cookie is secure via HMAC
     *
     * @return bool
     */
    public function isSecure()
    {
        $hmac_string = $this->cookieData.$this->pxCtx->getUserAgent();

        if ($this->isHmacValid($hmac_string, $this->getHmac())) {
            return true;
        }

        return false;
    }

}
