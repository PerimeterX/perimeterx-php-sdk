<?php
/** Copyright © 2016 PerimeterX, Inc.
 ** Permission is hereby granted, free of charge, to any
 ** person obtaining a copy of this software and associated
 ** documentation files (the "Software"), to deal in the
 ** Software without restriction, including without limitation
 ** the rights to use, copy, modify, merge, publish,
 ** distribute, sublicense, and/or sell copies of the
 ** Software, and to permit persons to whom the Software is
 ** furnished to do so, subject to the following conditions:
 **
 ** The above copyright notice and this permission notice
 ** shall be included in all copies or substantial portions of
 ** the Software.
 **
 ** THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY
 ** KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 ** WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 ** PURPOSE AND NONINFINGEMENT. IN NO EVENT SHALL THE AUTHORS
 ** OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR
 ** OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
 ** OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 ** SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Perimeterx;

use Psr\Log\LoggerInterface;

final class Perimeterx
{
    /**
     * @var array pxConfig risk cookie.
     */
    protected $pxConfig;

    /**
     * @var PerimeterxActivitiesClient activities client.
     */
    protected $pxActivitiesClient;

    private static $instance;

    public static $MONITOR_MODE = 1;
    public static $ACTIVE_MODE = 2;
    public static $MOBILE_SDK_HEADER = "X-PX-AUTHORIZATION";

    /**
     * Call this method to get singleton
     * @param array
     * @return Perimeterx
     */
    public static function Instance(array $pxConfig = [])
    {
        if (self::$instance === null) {
            self::$instance = new Perimeterx($pxConfig);
        }
        return self::$instance;
    }

    private function __construct(array $pxConfig = [])
    {
        if (!function_exists("mcrypt_encrypt")) {
            throw new PerimeterxException(PerimeterxException::$MCRYPT_MISSING);
        }
        if (!isset($pxConfig['app_id'])) {
            throw new PerimeterxException(PerimeterxException::$APP_ID_MISSING);
        }
        if (!isset($pxConfig['cookie_key'])) {
            throw new PerimeterxException(PerimeterxException::$COOKIE_MISSING);
        }
        if (!isset($pxConfig['auth_token'])) {
            throw new PerimeterxException(PerimeterxException::$AUTH_TOKEN_MISSING);
        }
        if (isset($this->pxConfig['logger']) && !($this->pxConfig['logger'] instanceof LoggerInterface)) {
            throw new PerimeterxException(PerimeterxException::$INVALID_LOGGER);
        }

        try {
            $this->pxConfig = array_merge([
                'app_id' => null,
                'cookie_key' => null,
                'auth_token' => null,
                'module_enabled' => true,
                'captcha_enabled' => true,
                'challenge_enabled' => true,
                'encryption_enabled' => true,
                'blocking_score' => 70,
                'sensitive_headers' => ['cookie', 'cookies'],
                'max_buffer_len' => 1,
                'send_page_activities' => false,
                'send_block_activities' => true,
                'sdk_name' => 'PHP SDK v2.5.1',
                'debug_mode' => false,
                'perimeterx_server_host' => 'https://sapi-' . strtolower($pxConfig['app_id']) . '.perimeterx.net',
                'module_mode' => Perimeterx::$ACTIVE_MODE,
                'api_timeout' => 1,
                'api_connect_timeout' => 1,
                'local_proxy' => false,
                'sensitive_routes' => []
            ], $pxConfig);

            if (empty($this->pxConfig['logger'])) {
                $this->pxConfig['logger'] = new PerimeterxLogger();
            }

            $httpClient = new PerimeterxHttpClient($this->pxConfig);
            $this->pxConfig['http_client'] = $httpClient;
            $this->pxActivitiesClient = new PerimeterxActivitiesClient($this->pxConfig);
        } catch (\Exception $e) {
            throw new PerimeterxException('Uncaught exception ' . $e->getCode() . ' ' . $e->getMessage());
        }
    }


    public function pxVerify()
    {
        $pxCtx = null;
        try {
            if (!$this->pxConfig['module_enabled']) {
                return 1;
            }

            $pxCtx = new PerimeterxContext($this->pxConfig);

            $captchaValidator = new PerimeterxCaptchaValidator($pxCtx, $this->pxConfig);
            if ($captchaValidator->verify()) {
                return $this->handleVerification($pxCtx);
            };

            $validator = new PerimeterxCookieValidator($pxCtx, $this->pxConfig);

            if (!$validator->verify()) {
                $s2sValidator = new PerimeterxS2SValidator($pxCtx, $this->pxConfig);
                $s2sValidator->verify();
            };
            return $this->handleVerification($pxCtx);
        } catch (\RuntimeException $e) {
            if (!empty($pxCtx)) {
                $pxCtx->setPassReason('error');
                $this->pxActivitiesClient->sendPageRequestedActivity($pxCtx);
            }
            $this->pxConfig['logger']->error('Uncaught exception while verifying perimeterx score ' . $e->getCode() . ' ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * @param PerimeterxContext $pxCtx
     * @return bool - a true value if captcha need to be displayed
     */
    private function shouldDisplayCaptcha($pxCtx)
    {
        return $this->pxConfig['captcha_enabled'] && $pxCtx->getBlockAction() == 'captcha';
    }

    /**
     * @param PerimeterxContext $pxCtx
     * @return bool - a true value if a challenge need to be displayed
     */
    private function shouldDisplayChallenge($pxCtx)
    {
        return $this->pxConfig['challenge_enabled'] && $pxCtx->getBlockAction() == 'challenge';
    }

    /**
     * @param PerimeterxContext $pxCtx
     * @return bool - a true value when user is scored ok/blocking is disabled
     */
    private function handleVerification($pxCtx)
    {
        $score = $pxCtx->getScore();
        /* score is ok - PASS traffic */
        if (!isset($score) or $score < $this->pxConfig['blocking_score']) {
            $this->pxActivitiesClient->sendPageRequestedActivity($pxCtx);
            return 1;
        }

        $this->pxActivitiesClient->sendBlockActivity($pxCtx);
        /* custom_block_handler - custom block handler defined by the user */
        if (isset($this->pxConfig['custom_block_handler'])) {
            $this->pxConfig['custom_block_handler']($pxCtx);
            return 1;
        }

        /* DEPRECATED - custom block handler defined by the user as a user function */
        if (function_exists('pxCustomBlockHandler')) {
            $this->pxConfig['logger']->warning("Deprecation Warning: please using pxConfig['custom_block_handler'] to custom your block handler instead of pxCustomBlockHandler");
            call_user_func('pxCustomBlockHandler', $pxCtx);
            return 1;
        }

        if ($this->pxConfig['module_mode'] != Perimeterx::$ACTIVE_MODE) {
            return 1;
        }

        $block_uuid = $pxCtx->getUuid();
        $mustache = new \Mustache_Engine(array(
            'loader' => new \Mustache_Loader_FilesystemLoader(dirname(__FILE__) . '/templates'),
        ));
        $templateInputs = array(
            'refId' => $block_uuid,
            'appId' => $this->pxConfig['app_id'],
            'vid' => $pxCtx->getVid(),
            'uuid' => $block_uuid,
            'logoVisibility' => isset($this->pxConfig['custom_logo']) ? 'visible' : 'hidden',
            'customLogo' => isset($this->pxConfig['custom_logo']) ? $this->pxConfig['custom_logo'] : '',
            'cssRef' => $this->getCssRef(),
            'jsRef' => $this->getJsRef()
        );


        /* generate return HTML */
        if ($this->shouldDisplayChallenge($pxCtx)) {
            /* set return html to challenge page */
            $html = $pxCtx->getBlockActionData();
        } elseif ($this->shouldDisplayCaptcha($pxCtx)) {
            /* set return html to default captcha page */
            $html = $pxCtx->getCookieOrigin() == 'cookie' ? $mustache->render('captcha', $templateInputs) : $mustache->render('captcha.mobile', $templateInputs);
        } else {
            /* set return html to default block page */
            $html = $mustache->render('block', $templateInputs);
        }

        header("Status: 403");
        if ($pxCtx->getCookieOrigin() == 'cookie') {
            header("Content-Type: text/html");
            echo $html;
        } else {
            header("Content-Type: application/json");
            $result = array(
                'action' => $pxCtx->getBlockAction(),
                'uuid' => $block_uuid,
                'vid' => $pxCtx->getVid(),
                'appId' => $this->pxConfig['app_id'],
                'page' => base64_encode($html),
                'collectorUrl' => $this->pxConfig['perimeterx_server_host']
            );
            echo json_encode($result);
        }
        die();
    }

    /**
     * Method for retreving jsRef from pxConfig if exists
     */
    private function getJsRef()
    {
        $jsRefScript = '';
        if (isset($this->pxConfig['js_ref'])) {
            $jsRefScript = $this->pxConfig['js_ref'];
        }
        return $jsRefScript;
    }


    /**
     * Method for retreving cssRef from pxConfig if exists
     */
    private function getCssRef()
    {
        $cssRefScript = "";
        if (isset($this->pxConfig['css_ref'])) {
            $cssRefScript = $this->pxConfig['css_ref'];
        }

        return $cssRefScript;
    }

    /**
     * Public function that contact PerimeterX servers and reset user's score from cache. can be used as part of internal flows
     */
    public function pxReset()
    {
        try {
            if (!$this->pxConfig['module_enabled']) {
                return 1;
            }

            $pxCtx = new PerimeterxContext($this->pxConfig);
            $cookie = PerimeterxCookie::pxCookieFactory($pxCtx, $this->pxConfig);
            if ($cookie->isValid()) {
                $pxCtx->setVid($cookie->getVid());
                $pxCtx->setUuid($cookie->getUuid());
            }

            $client = new PerimeterxResetClient($pxCtx, $this->pxConfig);
            $client->sendResetRequest();
        } catch (Exception $e) {
            $this->pxConfig['logger']->error('Uncaught exception while resetting perimeterx score' . $e->getCode() . ' ' . $e->getMessage());
        }
        return 1;
    }

    /**
     * @return string
     */
    public function getPxConfig()
    {
        return $this->pxConfig;
    }
}
