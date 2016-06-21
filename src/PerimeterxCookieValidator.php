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
     * @param PerimeterxContext - perimeterx context
     * @param array - perimeterx configurations
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
        $pad = ord( $str[$len - 1] );
        if ($pad && $pad < 16) {
            $pm = preg_match('/' . chr($pad) . '{' . $pad . '}$/', $str);
            if( $pm ) {
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
                $this->pxCtx->setS2SCallReason('cookie_missing');
                return false;
            }

            if ($this->pxConfig['encryption_enabled']) {
                $cookie = $this->decrypt();
            } else {
                $cookie = $this->decode();
            }
            $cookie = json_decode($cookie);
            $c_time = $cookie->t;
            $c_score = $cookie->s;
            $c_uuid = $cookie->u;
            $c_vid = $cookie->v;
            $c_hmac = $cookie->h;

            if (!isset($c_time, $c_score, $c_score->b, $c_uuid, $c_vid, $c_hmac)) {
                error_log('invalid cookie');
                $this->pxCtx->setS2SCallReason('cookie_invalid');
                return false;
            }
            $this->pxCtx->setScore($c_score->b);
            $this->pxCtx->setUuid($c_uuid);
            $this->pxCtx->setVid($c_vid);
            if ($c_score->b >= $this->pxConfig['blocking_score']) {
                error_log('cookie high score');
                $this->pxCtx->setBlockReason('cookie_high_score');
                $this->pxCtx->setScore($c_score->b);
                return true;
            };

            $dataTimeSec = $c_time / 1000;
            if ($dataTimeSec < time()) {
                error_log('cookie expired');
                $this->pxCtx->setS2SCallReason('cookie_expired');
                return false;
            }
            $hmac_str = $c_time . $c_score->a . $c_score->b . $c_uuid . $c_vid . $this->pxCtx->getIp() . $this->pxCtx->getUserAgent();
            $hmac = hash_hmac('sha256', $hmac_str, $this->cookieSecret);
            if ($hmac == $c_hmac) {
                error_log('cookie ok');
                $this->pxCtx->setScore($c_score->b);
                return true;
            } else {
                error_log('cookie invalid hmac');
                $this->pxCtx->setS2SCallReason('cookie_invalid');
                return false;
            }
        } catch (\Exception $e) {
            error_log('exception while verifying cookie');
            $this->pxCtx->setS2SCallReason('cookie_invalid');
            return false;
        }

    }
}
