<?php

namespace Perimeterx;

class PerimeterxOriginalTokenValidator
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

    public function __construct($pxCtx, $pxConfig)
    {
        $delimiter = ":";
        $pxOrigToken = $pxCtx->getOriginalToken();
        if (strpos($pxOrigToken, $delimiter)) {
            list($key, $token) = explode($delimiter, $pxOrigToken, 2);
            $this->pxToken = $token;
        }

        $this->pxConfig = $pxConfig;
        $this->pxCtx = $pxCtx;
    }

    /**
     * @return bool - main verification function, decrypts a token and verifies its content
     */
    public function verify()
    {
        try {
            $payload = new TokenV3($this->pxCtx, $this->pxConfig, $this->pxToken);
            $this->pxConfig['logger']->debug("Original token found, evaluating");
            if (!$payload->deserialize()) {
                $this->pxConfig['logger']->debug("Original token decryption failed, value: {$this->pxToken}");
                $this->pxCtx->setOriginalTokenError('cookie_decryption_failed');
                return false;
            }

            $this->pxCtx->setDecodedOriginalToken($payload->getDecodedPayload());
            $this->pxCtx->setOriginalTokenUuid($payload->getUuid());
            $this->pxCtx->setVid($payload->getVid());

            if (!$payload->isSecure()) {
                $payloadString = json_encode($payload->getDecodedPayload());
                $this->pxConfig['logger']->debug("Cookie HMAC validation failed, value: $payloadString, user-agent: {$this->pxCtx->getUserAgent()}");
                $this->pxCtx->setOriginalTokenError('cookie_validation_failed');
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->pxConfig['logger']->error("Unexpected exception while evaluating original token: {$e->getMessage()}");
            return false;
        }
    }
}