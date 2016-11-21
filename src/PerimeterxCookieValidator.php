<?php
namespace Perimeterx;

class PerimeterxCookieValidator
{
    /**
     * @var string
     */
    private $pxCookie;

    private $cookieSecret;

    /**
     * @var PerimeterxContext
     */
    private $pxCtx;

    /**
     * @var object - perimeterx configuration object
     */
    private $pxConfig;

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
     * @return bool - main verification function, decrypt/decode cookie if exists and verify its content
     */
    public function verify()
    {
        try {
            if (!isset($this->pxCookie)) {
                $this->pxCtx->setS2SCallReason('no_cookie');
                return false;
            }

            if ($this->pxConfig['encryption_enabled']) {
                $cookie = $this->decrypt();
            } else {
                $cookie = $this->decode();
            }
            $cookie = json_decode($cookie);
            if ($cookie == NULL) {
                $this->pxCtx->setS2SCallReason('cookie_decryption_failed');
                return false;
            }
            $c_time = $cookie->t;
            $c_score = $cookie->s;
            $c_uuid = $cookie->u;
            $c_vid = $cookie->v;
            $c_hmac = $cookie->h;

            if (!isset($c_time, $c_score, $c_score->b, $c_uuid, $c_vid, $c_hmac)) {
                $this->pxConfig['logger']->warning('invalid cookie');
                $this->pxCtx->setS2SCallReason('cookie_decryption_failed');
                return false;
            }

            $dataTimeSec = $c_time / 1000;
            if ($dataTimeSec < time()) {
                $this->pxConfig['logger']->info('cookie expired');
                $this->pxCtx->setS2SCallReason('cookie_expired');
                return false;
            }

            $this->pxCtx->setDecodedCookie($cookie);
            $this->pxCtx->setScore($c_score->b);
            $this->pxCtx->setUuid($c_uuid);
            $this->pxCtx->setVid($c_vid);
            if ($c_score->b >= $this->pxConfig['blocking_score']) {
                $this->pxConfig['logger']->info('cookie high score');
                $this->pxCtx->setBlockReason('cookie_high_score');
                $this->pxCtx->setScore($c_score->b);
                return true;
            };

            /* hmac string with ip - for backward support */
            $hmac_str_withip = $c_time . $c_score->a . $c_score->b . $c_uuid . $c_vid . $this->pxCtx->getIp() . $this->pxCtx->getUserAgent();

            /* hmac string with no ip */
            $hmac_str_withoutip = $c_time . $c_score->a . $c_score->b . $c_uuid . $c_vid . $this->pxCtx->getUserAgent();

            if ($this->hmac_matches($hmac_str_withoutip, $c_hmac, $this->cookieSecret) or $this->hmac_matches($hmac_str_withip, $c_hmac, $this->cookieSecret)) {
                $this->pxConfig['logger']->info('cookie ok');
                $this->pxCtx->setScore($c_score->b);
                return true;
            } else {
                $this->pxConfig['logger']->warning('cookie invalid hmac');
                $this->pxCtx->setS2SCallReason('cookie_validation_failed');
                return false;
            }
        } catch (\Exception $e) {
            $this->pxConfig['logger']->error('exception while verifying cookie');
            $this->pxCtx->setS2SCallReason('cookie_decryption_failed');
            return false;
        }

    }

    private function hmac_matches($hmac_str, $cookie_hmac, $cookieSecret)
    {
        $hmac = hash_hmac('sha256', $hmac_str, $cookieSecret);

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
