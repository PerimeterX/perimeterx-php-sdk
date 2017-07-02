<?php

namespace Perimeterx;

class PerimeterxMobileTokenValidator
{
    /**
     * @var string
     */
    private $pxToken;

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
     * @param $pxTokenHeaderKey - the key of the header containing the mobile token
     */
    public function __construct($pxCtx, $pxConfig, $pxTokenHeaderKey)
    {
        $this->pxToken = $pxCtx->getHeaders()[$pxTokenHeaderKey];
        $this->pxConfig = $pxConfig;
        $this->pxCtx = $pxCtx;
    }

    /**
     * @return bool - main verification function, decrypt the token and verify its content
     */
    public function verify()
    {
        try {
            if ($this->pxToken == 1) {
                $this->pxCtx->setS2SCallReason('no_token');
                return false;
            }
            $token = PerimeterxToken::pxTokenFactory($this->pxCtx, $this->pxConfig, $this->pxToken);
            if (!$token->deserialize()) {
                $this->pxConfig['logger']->warning('invalid token');
                $this->pxCtx->setS2SCallReason('token_decryption_failed');
                return false;
            }

            $this->pxCtx->setDecodedToken($token->getDecodedToken());
            $this->pxCtx->setScore($token->getScore());
            $this->pxCtx->setUuid($token->getUuid());
            $this->pxCtx->setVid($token->getVid());
            $this->pxCtx->setBlockAction($token->getBlockAction());
            $this->pxCtx->setTokenHmac($token->getHmac());

            if ($token->isExpired()) {
                $this->pxConfig['logger']->info('token expired');
                $this->pxCtx->setS2SCallReason('token_expired');
                return false;
            }

            if ($token->isHighScore()) {
                $this->pxConfig['logger']->info('token high score');
                $this->pxCtx->setBlockReason('token_high_score');
                return true;
            }

            if (!$token->isSecure()) {
                $this->pxConfig['logger']->warning('token invalid hmac');
                $this->pxCtx->setS2SCallReason('token_validation_failed');
                return false;
            }

            // Case we have a sensitive route
            if ($this->pxCtx->isSensitiveRoute()) {
                $this->pxConfig['logger']->info('token verification passed, risk api triggered by sensitive route');
                $this->pxCtx->setS2SCallReason('sensitive_route');
                return false;
            }


        } catch (Exception $e) {
            $this->pxConfig['logger']->error('exception while verifying token');
            $this->pxCtx->setS2SCallReason('token_decryption_failed');
            return false;
        }
    }
}