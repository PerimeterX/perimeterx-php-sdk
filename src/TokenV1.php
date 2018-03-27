<?php

namespace Perimeterx;

class TokenV1 extends PerimeterxToken
{
    /**
     * @param $pxCtx PerimeterxContext - perimeterx context
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxCtx, $pxConfig, $payload)
    {
        $this->pxPayload = $payload;
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
        return $this->isHmacValid($base_hmac_str, $this->getHmac());
    }
}