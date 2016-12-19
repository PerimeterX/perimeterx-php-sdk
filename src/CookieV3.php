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

    public function getScore()
    {
        return $this->getDecodedCookie()->s;
    }

    protected function getHmac()
    {
        return $this->cookieHash;
    }

    protected function isCookieFormatValid($cookie) {
        return true;
        //return isset($cookie->t, $cookie->s, $cookie->s->b, $cookie->u, $cookie->v, $cookie->h);
    }

    /**
     * Checks if the cookie has expired
     *
     * @return bool
     */
    public function isExpired()
    {
        $dataTimeSec = $this->getTime() / 1000;

        return ($dataTimeSec < time());
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
