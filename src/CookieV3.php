<?php

namespace Perimeterx;

class CookieV3 extends PerimeterxCookie
{

    /**
     * @var string
     */
    private $pxCookie;

    /**
     * @var object - perimeterx configuration object
     */
    private $pxConfig;

    /**
     * @var PerimeterxContext
     */
    private $pxCtx;

    /**
     * @var string
     */
    private $cookieSecret;

    /**
     * @param $pxCtx PerimeterxContext - perimeterx context
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxCtx, $pxConfig)
    {
        //$this->pxCookie = $pxCtx->getPxCookie();
        //$this->pxConfig = $pxConfig;
        //$this->pxCtx = $pxCtx;
        //$this->cookieSecret = $pxConfig['cookie_key'];
    }

    /**
     * @var \stdClass
     */
    private $decodedCookie;

    public function getDecodedCookie()
    {
        return $this->decodedCookie;
    }

    public function getTime()
    {
        return $this->getDecodedCookie()->t;
    }

    public function getScore()
    {
        return $this->getDecodedCookie()->s;
    }

    public function getUuid()
    {
        return $this->getDecodedCookie()->u;
    }

    public function getVid()
    {
        return $this->getDecodedCookie()->v;
    }

    protected function getHmac()
    {
        return $this->cookieHash;
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

    /**
     * Deserializes an encrypted and/or encoded cookie string.
     *
     * This must be called before using an instance.
     *
     * @return bool
     */
    public function deserialize()
    {
        // only deserialize once
        if ($this->decodedCookie !== null) {
            return true;
        }

        if ($this->pxConfig['encryption_enabled']) {
            $cookie = $this->decrypt();
        } else {
            $cookie = $this->decode();
        }
        $cookie = json_decode($cookie);
        if ($cookie == null) {
            return false;
        }

        if (!isset($cookie->t, $cookie->s, $cookie->s->b, $cookie->u, $cookie->v, $cookie->h)) {
            return false;
        }

        $this->decodedCookie = $cookie;

        return true;
    }

    private function decrypt()
    {
        $ivlen = 16;
        $keylen = 32;
        $digest = 'sha256';

        $cookie = $this->pxCookie;
        list($salt, $iterations, $cookie) = explode(":", $cookie);
        $iterations = intval($iterations);
        $salt = base64_decode($salt);
        $cookie = base64_decode($cookie);


        $derivation = hash_pbkdf2($digest, $this->cookieSecret, $salt, $iterations, $ivlen + $keylen, true);
        $key = substr($derivation, 0, $keylen);
        $iv = substr($derivation, $keylen);
        $cookie = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $cookie, MCRYPT_MODE_CBC, $iv);

        return $this->unpad($cookie);
    }

    /**
     * @return string - decoded perimeterx cookie
     */
    private function decode()
    {
        $data_str = base64_decode($this->pxCookie);
        return json_decode($data_str);
    }
}
