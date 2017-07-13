<?php

namespace Perimeterx;

class PerimeterxCookieValidator
{
    /**
     * @var string
     */
    private $pxCookie;

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
    }

    /**
     * @return bool - main verification function, decrypt/decode cookie if exists and verify its content
     */
    public function verify()
    {
        try {
            if (!isset($this->pxCookie) || (isset($this->pxCookie) && $this->pxCtx->getCookieOrigin() == "header" && $this->pxCookie == 1)) {
                $this->pxConfig['logger']->info('no cookie');
                $this->pxCtx->setS2SCallReason('no_cookie');
                return false;
            }

            $cookie = PerimeterxPayload::pxPayloadFactory($this->pxCtx, $this->pxConfig);
            if (!$cookie->deserialize()) {
                $this->pxConfig['logger']->warning('invalid cookie');
                $this->pxCtx->setS2SCallReason('cookie_decryption_failed');
                return false;
            }

            $this->pxCtx->setDecodedCookie($cookie->getDecodedPayload());
            $this->pxCtx->setScore($cookie->getScore());
            $this->pxCtx->setUuid($cookie->getUuid());
            $this->pxCtx->setVid($cookie->getVid());
            $this->pxCtx->setBlockAction($cookie->getBlockAction());
            $this->pxCtx->setCookieHmac($cookie->getHmac());

            if ($cookie->isExpired()) {
                $this->pxConfig['logger']->info('cookie expired');
                $this->pxCtx->setS2SCallReason('cookie_expired');
                return false;
            }

            if ($cookie->isHighScore()) {
                $this->pxConfig['logger']->info('cookie high score');
                $this->pxCtx->setBlockReason('cookie_high_score');
                return true;
            }

            if (!$cookie->isSecure()) {
                $this->pxConfig['logger']->warning('cookie invalid hmac');
                $this->pxCtx->setS2SCallReason('cookie_validation_failed');
                return false;
            }

            // Case we have a sensitive route
            if ($this->pxCtx->isSensitiveRoute()) {
                $this->pxConfig['logger']->info('cookie verification passed, risk api triggered by sensitive route');
                $this->pxCtx->setS2SCallReason('sensitive_route');
                return false;
            }

            $this->pxCtx->setPassReason('cookie');
            $this->pxConfig['logger']->info('cookie ok');

            return true;
        } catch (\Exception $e) {
            $this->pxConfig['logger']->error('exception while verifying cookie');
            $this->pxCtx->setS2SCallReason('cookie_decryption_failed');
            return false;
        }
    }
}
