<?php

namespace Perimeterx;

class CookieV1 extends PerimeterxCookie
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
        $this->pxCookie = $pxCtx->getPxCookie();
        $this->pxConfig = $pxConfig;
        $this->pxCtx = $pxCtx;
        $this->cookieSecret = $pxConfig['cookie_key'];
    }

    /**
     * @var \stdClass
     */
    private $decodedCookie;

    public getPxCookie() {
        return $this->pxCookie;
    }

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
        return $this->getDecodedCookie()->s->b;
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
        return $this->getDecodedCookie()->h;
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

    private function unpad($str)
    {
        $len = mb_strlen($str);
        $pad = ord($str[$len - 1]);
        if ($pad && $pad < 16) {
            $pm = preg_match('/' . chr($pad) . '{' . $pad . '}$/', $str);
            if ($pm) {
                return mb_substr($str, 0, $len - $pad);
            }
        }
        return $str;
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
