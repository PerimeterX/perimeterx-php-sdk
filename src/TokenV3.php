<?php

namespace Perimeterx;

class TokenV3 extends PerimeterxToken
{
    /**
     * @param $pxCtx PerimeterxContext - perimeterx context
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxCtx, $pxConfig, $payload)
    {
        if ($pxConfig['encryption_enabled']) {
            $cookieValidPartsNumber = 4;
        } else {
            $cookieValidPartsNumber = 2;
        }



        $payloadParts = explode(":", $payload);
        if (count($payloadParts) < $cookieValidPartsNumber) {
            return null;
        }
        list($hash, $token) = explode(":", $payload, 2);

        $this->pxPayload = $token;
        $this->tokenHash = $hash;
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
        return $this->tokenHash;
    }

    protected function isCookieFormatValid($token) {
        return isset($token->t, $token->s, $token->u, $token->v, $token->a);
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
        return $this->isHmacValid($this->pxPayload, $this->getHmac());
    }
}