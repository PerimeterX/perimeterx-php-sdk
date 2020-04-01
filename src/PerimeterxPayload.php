<?php

namespace Perimeterx;

abstract class PerimeterxPayload {

    /**
     * @var string
     */
    protected $pxPayload;

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
    protected $payloadSecret;

    /**
     * Factory method for creating PX payload object according to the version found on the request
     */
    public static function pxPayloadFactory($pxCtx, $pxConfig) {
        if ($pxCtx->getCookieOrigin() == "cookie") {
            return (isset($pxCtx->getPxCookies()['v3']) ? new CookieV3($pxCtx, $pxConfig) : new CookieV1($pxCtx, $pxConfig));
        } else {
            return (isset($pxCtx->getPxCookies()['v3']) ? new TokenV3($pxCtx, $pxConfig, $pxCtx->getPxCookie()) : new TokenV1($pxCtx, $pxConfig, $pxCtx->getPxCookie()));
        }
    }

    /** @var \stdClass
     */
    protected $decodedPayload;

    public function getDecodedPayload() {
        return $this->decodedPayload;
    }

    protected function getPayload() {
        return $this->pxPayload;
    }

    public function getTime() {
        return $this->getDecodedPayload()->t;
    }

    abstract protected function getScore();

    public function getUuid() {
        return $this->getDecodedPayload()->u;
    }

    public function getVid() {
        return $this->getDecodedPayload()->v;
    }

    abstract public function getHmac();

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

    public function deserialize() {
        // only deserialize once
        if ($this->decodedPayload !== null) { return true; }

        if ($this->pxConfig['encryption_enabled']) {
            $payload = $this->decrypt();
        } else {
            $payload = $this->decode();
        }
        $payload = json_decode($payload);

        if ($payload == null) { return false; }

        if (!$this->isCookieFormatValid($payload)) {
            return false;
        }

        $this->decodedPayload = $payload;

        return true;
    }

    private function decrypt()
    {
        $ivlen = 16;
        $keylen = 32;
        $digest = 'sha256';

        $payload = $this->getPayload();

        $payloadParts = explode(":", $payload);
        if (count($payloadParts) < 3) {
            return null;
        }

        list($salt, $iterations, $payload) = $payloadParts;
        $iterations = intval($iterations);

        if ($iterations < 1 || $iterations >= 5000) {
            return null;
        }

        $salt = base64_decode($salt);
        $payload = base64_decode($payload);

        $derivation = hash_pbkdf2($digest, $this->cookieSecret, $salt, $iterations, $ivlen + $keylen, true);
        $key = substr($derivation, 0, $keylen);
        $iv = substr($derivation, $keylen);
        $payload = openssl_decrypt($payload, "aes-256-cbc", $key, OPENSSL_RAW_DATA, $iv);
        if (!$payload) {
            return null;
        }

        return $this->unpad($payload);
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

        $str = trim($str, "\x00..\x1F"); // removes unprintable whitespaces
        return $str;
    }

    /**
     * @return string - decoded perimeterx cookie
     */
    private function decode()
    {
        $data_str = base64_decode($this->getPayload());
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