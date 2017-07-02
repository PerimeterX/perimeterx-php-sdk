<?php

namespace Perimeterx;

abstract class PerimeterxToken {

    /**
     * @var string
     */
    protected $pxToken;

    /**
     * @var object - perimeterx configuration object
     */
    protected $pxConfig;

    /**
     * @var PerimeterxContext
     */
    protected $pxCtx;

    /**
     * Factory method for creating PX Cookie object according to cookie version found on the request
     */
    public static function pxTokenFactory($pxCtx, $pxConfig, $token) {
        $tokenVersion = explode(":", $token)[0];
        return ($tokenVersion == 3 ? new TokenV3($pxCtx, $pxConfig) : new TokenV1($pxCtx, $pxConfig));
    }

    /** @var \stdClass
     */
    protected $decodedToken;

    public function getDecodedToken() {
        return $this->decodedToken;
    }

    protected function getToken() {
        return $this->pxToken;
    }

    public function getTime() {
        return $this->getDecodedToken()->t;
    }

    public function getUuid() {
        return $this->getDecodedToken()->u;
    }

    public function getVid() {
        return $this->getDecodedToken()->v;
    }

    abstract protected function getScore();

    abstract public function getHmac();

    abstract protected function isTokenFormatValid($token);

    abstract public function getBlockAction();

    /** Checks if the token's score is above the configured blocking score
     *
     * @return bool
     */
    public function isHighScore() {
        return ($this->getScore() >= $this->pxConfig['blocking_score']);
    }

    /** Checks if the token has expired
     *
     * @return bool
     */
    public function isExpired() {
        $dataTimeSec = $this->getTime() / 1000;
        return ($dataTimeSec < time());
    }

    /** Checks that the token is secure via HMAC
     *
     * @return bool
     */
    abstract public function isSecure();

    /** Checks that the token was deserialized succcessfully, has not expired,
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
        if ($this->decodedToken !== null) { return true; }

        if ($this->pxConfig['encryption_enabled']) {
            $token = $this->decrypt();
        } else {
            $token = $this->decode();
        }
        $token = json_decode($token);

        if ($token == null) { return false; }

        if (!$this->isTokenFormatValid($token)) {
            return false;
        }

        $this->decodedToken = $token;

        return true;
    }

    private function decrypt()
    {
        $ivlen = 16;
        $keylen = 32;
        $digest = 'sha256';

        $token = $this->getToken();
        $this->pxConfig['logger']->info($token);
        list($salt, $iterations, $token) = explode(":", $token);
        $iterations = intval($iterations);
        $salt = base64_decode($salt);
        $token = base64_decode($token);


        $derivation = hash_pbkdf2($digest, $this->cookieSecret, $salt, $iterations, $ivlen + $keylen, true);
        $key = substr($derivation, 0, $keylen);
        $iv = substr($derivation, $keylen);
        $token = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $token, MCRYPT_MODE_CBC, $iv);

        return $this->unpad($token);
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
        $data_str = base64_decode($this->pxToken);
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


