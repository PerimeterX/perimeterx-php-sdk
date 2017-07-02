<?php

namespace Perimeterx;

class TokenV3 extends PerimeterxToken
{
    /**
     * @param $pxCtx PerimeterxContext - perimeterx context
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxCtx, $pxConfig)
    {
        list($hash, $token) = explode(":", $pxCtx->getPxToken(), 2);
        $this->pxToken = $token;
        $this->tokenHash = $hash;
        $this->pxConfig = $pxConfig;
        $this->pxCtx = $pxCtx;
        $this->cookieSecret = $pxConfig['cookie_key'];
    }

    public function getScore()
    {
        return $this->getDecodedToken()->s;
    }

    public function getHmac()
    {
        return $this->tokenHash;
    }

    protected function isTokenFormatValid($token) {
        return isset($token->t, $token->s, $token->u, $token->v, $token->a);
    }

    public function getBlockAction() {
        return $this->getDecodedToken()->a;
    }

    /**
     * Checks that the cookie is secure via HMAC
     *
     * @return bool
     */
    public function isSecure()
    {
        return $this->isHmacValid($this->pxToken, $this->getHmac());
    }
}