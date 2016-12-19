<?php

namespace Perimeterx;

abstract class PerimeterxCookie {

    /**
     * @var string
     */
    protected $pxCookie;

    /**
     * @var object - perimeterx configuration object
     */
    protected $pxConfig;

    /**
     * @var PerimeterxContext
     */
    protected $pxCtx;

    /**
     * @var string
     */
    protected $cookieSecret;

    public static function createPXCookieObject($pxCtx, $pxConfig) {
        if (isset($pxCtx->getPxCookie()['v3'])) {
            return new CookieV3($pxCtx, $pxConfig);
        }
        return new CookieV1($pxCtx, $pxConfig);
    }

    /** @var \stdClass
     */
    protected $decodedCookie;

    public function getDecodedCookie() {
        return $this->decodedCookie;
    }

    protected function getCookie() {
        return $this->pxCookie;
    }

    public function getTime() {
        return $this->getDecodedCookie()->t;
    }

    abstract protected function getScore();

    public function getUuid() {
        return $this->getDecodedCookie()->u;
    }

    public function getVid() {
        return $this->getDecodedCookie()->v;
    }

    abstract protected function getHmac();

    abstract protected function isCookieFormatValid($cookie);

    abstract public function getBlockAction();

    /** Checks if the cookie's score is above the configured blocking score
     *
     * @return bool
     */
    public function isHighScore() {
        return ($this->getScore() >= $this->pxConfig['blocking_score']);
    }

    /** Checks if the cookie has expired
     *
     * @return bool
     */
    public function isExpired() {
        $dataTimeSec = $this->getTime() / 1000;
        return ($dataTimeSec < time());
    }

    /** Checks that the cookie is secure via HMAC
     *
     * @return bool
     */
    abstract public function isSecure();

    /** Checks that the cookie was deserialized succcessfully, has not expired,
     * and is secure
     *
     * @return bool
     */
    public function isValid() {
        return $this->deserialize() && !$this->isExpired() && $this->isSecure();
    }

    /** Deserializes an encrypted and/or encoded cookie string.
     *
     * This must be called before using an instance.
     *
     * @return bool
     */
    public function deserialize() {
        // only deserialize once
        if ($this->decodedCookie !== null) { return true; }

        if ($this->pxConfig['encryption_enabled']) {
            $cookie = $this->decrypt();
        } else {
            $cookie = $this->decode();
        }
        $cookie = json_decode($cookie); if ($cookie == null) { return false; }

        if (!$this->isCookieFormatValid($cookie)) {
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

        $cookie = $this->getCookie();
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
        $data_str = base64_decode($this->getPxCookie());
        return json_decode($data_str);
    }

    protected function isHmacValid($hmac_str, $cookie_hmac)
    {
        $hmac = hash_hmac('sha256', $hmac_str, $this->cookieSecret);

        if (function_exists('hash_equals')) {
            return hash_equals($hmac, $cookie_hmac);
        }

        // @see http://php.net/manual/en/function.hash-equals.php#115635
        if (strlen($hmac) != strlen($cookie_hmac)) {
            return false;
        } else {
            $res = $hmac ^ $cookie_hmac;
            $ret = false;
            for ($i = strlen($res) - 1; $i >= 0; $i--) {
                $ret |= ord($res[$i]);
            }

            return !$ret;
        }
    }
}
