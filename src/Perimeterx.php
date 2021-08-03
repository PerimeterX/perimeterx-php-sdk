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

    /**
     * @var PerimeterxFieldExtractorManager
     */
    private $pxFieldExtractorManager;

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
                'guzzle_handler' => null,
                'module_enabled' => true,
                'captcha_enabled' => true,
                'challenge_enabled' => true,
                'encryption_enabled' => true,
                'blocking_score' => 100,
                'sensitive_headers' => ['cookie', 'cookies'],
                'max_buffer_len' => 1,
                'send_page_activities' => true,
                'send_block_activities' => true,
                'sdk_name' => 'PHP SDK v3.7.2',
                'debug_mode' => false,
                'perimeterx_server_host' => 'https://sapi-' . strtolower($pxConfig['app_id']) . '.perimeterx.net',
                'captcha_script_host' => 'https://captcha.px-cdn.net',
                'module_mode' => Perimeterx::$MONITOR_MODE,
                'api_timeout' => 1,
                'api_connect_timeout' => 1,
                'activities_timeout' => 1,
                'activities_connect_timeout' => 1,
                'local_proxy' => false,
                'sensitive_routes' => [],
                'ip_headers' => [],
                'bypass_monitor_header' => null,
                'custom_block_url' => null,
                'defer_activities' => true,
                'enable_json_response' => false,
                'return_response' => false
            ], $pxConfig);

            if (empty($this->pxConfig['logger'])) {
                $this->pxConfig['logger'] = new PerimeterxLogger($this->pxConfig);
            }

            $httpClient = new PerimeterxHttpClient($this->pxConfig);
            $this->pxConfig['http_client'] = $httpClient;
            $this->pxActivitiesClient = new PerimeterxActivitiesClient($this->pxConfig);
            $this->pxFieldExtractorManager = $this->createFieldExtractorManager();
        } catch (\Exception $e) {
            throw new PerimeterxException('Uncaught exception ' . $e->getCode() . ' ' . $e->getMessage());
        }
    }


    public function pxVerify()
    {
        $pxCtx = null;
        $extractedCredentials = null;
        $this->pxConfig['logger']->debug('Starting request verification');
        try {
            if (!$this->pxConfig['module_enabled']) {
                $this->pxConfig['logger']->debug('Request will not be verified, module is disabled');
                return 1;
            }
            
            if (!is_null($this->pxFieldExtractorManager)) {
                $extractedCredentials = $this->pxFieldExtractorManager->extractFields();
            }

            $pxCtx = new PerimeterxContext($this->pxConfig, $extractedCredentials);
            $this->pxConfig['logger']->debug('Request context created successfully');

            $validator = new PerimeterxCookieValidator($pxCtx, $this->pxConfig);

            $cookie_valid = $validator->verify();
            if($cookie_valid) {
                PerimeterxDataEnrichment::processDataEnrichment($pxCtx, $this->pxConfig);
            }
            else {
                $s2sValidator = new PerimeterxS2SValidator($pxCtx, $this->pxConfig);
                $s2sValidator->verify();
            }
            return $this->handleVerification($pxCtx);
        } catch (\RuntimeException $e) {
            if (!empty($pxCtx)) {
                $pxCtx->setS2SError("unknown_error", "Error {$e->getCode()}: {$e->getMessage()}");
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
     * @return bool - a true value if rate limit template need to be displayed
     */
    private function shouldDisplayRateLimit($pxCtx)
    {
        return $pxCtx->getBlockAction() == 'ratelimit';
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
     * @return mixed object|boolean as the verification result
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

        $headers = array_change_key_case($pxCtx->getHeaders(), CASE_LOWER);
        $should_bypass_monitor = isset($this->pxConfig['bypass_monitor_header']) && isset($headers[strtolower($this->pxConfig['bypass_monitor_header'])]) && $headers[strtolower($this->pxConfig['bypass_monitor_header'])] == "1";
        if ($this->pxConfig['module_mode'] != Perimeterx::$ACTIVE_MODE && !$should_bypass_monitor ) {
            return 1;
        }

        $accept_header = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : $_SERVER['HTTP_CONTENT_TYPE'];
        $is_json_response = $pxCtx->getCookieOrigin() == 'cookie' && strpos($accept_header,'application/json') !== false && $this->pxConfig['enable_json_response'];

        $block_uuid = $pxCtx->getUuid();
        $mustache = new \Mustache_Engine(array(
            'loader' => new \Mustache_Loader_FilesystemLoader(dirname(__FILE__) . '/templates'),
        ));

        $collectorUrl = 'https://collector-' . strtolower($this->pxConfig['app_id']) . '.perimeterx.net';
        $blockScript = $this->getCaptchaScript($this->pxConfig, $pxCtx);

        $templateInputs = array(
            'refId' => $block_uuid,
            'appId' => $this->pxConfig['app_id'],
            'vid' => $pxCtx->getVid(),
            'uuid' => $block_uuid,
            'logoVisibility' => isset($this->pxConfig['custom_logo']) ? 'visible' : 'hidden',
            'customLogo' => isset($this->pxConfig['custom_logo']) ? $this->pxConfig['custom_logo'] : '',
            'cssRef' => $this->getCssRef(),
            'jsRef' => $this->getJsRef(),
            'hostUrl' => $collectorUrl,
            'blockScript' => $blockScript,
            'jsClientSrc' => "//client.perimeterx.net/{$this->pxConfig['app_id']}/main.min.js"
        );

        http_response_code(403);
        if ($this->shouldDisplayChallenge($pxCtx)) {
            /* set return html to challenge page */
            $html = $pxCtx->getBlockActionData();
            $this->pxConfig['logger']->debug("Enforcing action: Challenge page is served");
        } elseif ($this->shouldDisplayRateLimit($pxCtx)) {
            http_response_code(429);
            $html = $mustache->render('ratelimit');
            $this->pxConfig['logger']->debug("Enforcing action: Rate limit page is served");
        } else {
            /* set return html to default block page */
            if (isset($this->pxConfig['custom_block_url'])) {
                $url = base64_encode($pxCtx->getUri());
                $page_url = $this->pxConfig['custom_block_url'].'?vid='.$templateInputs['vid'].'&uuid='.$templateInputs['uuid'].'&url='.$url;
                header('Location: '.$page_url, true, 307);
                die();
            } else {
                if ($is_json_response == false) {
                    $html = $mustache->render('block_template', $templateInputs);
                    $this->pxConfig['logger']->debug("Enforcing action: {$pxCtx->getBlockAction()} page is served");
                } else {
                    $this->pxConfig['logger']->debug("Enforcing action: advanced blocking response is served");
                }
            }
        }

        if ($pxCtx->getCookieOrigin() == 'cookie') {
            if($is_json_response) {
                header("Content-Type: application/json");
                $result = array(
                    'appId' => $this->pxConfig['app_id'],
                    'jsClientSrc' => $templateInputs['jsClientSrc'],
                    'firstPartyEnabled' => false,
                    'vid' => $templateInputs['vid'],
                    'uuid' => $templateInputs['uuid'],
                    'hostUrl' => $templateInputs['hostUrl'],
                    'blockScript' => $templateInputs['blockScript']
                );
                if ($this->pxConfig['return_response']) {
                    return $result;
                }
                echo json_encode($result);
            } else {
                header("Content-Type: text/html");
                if ($this->pxConfig['return_response']) {
                    return $html;
                }
                echo $html;
            }
        } else {
            header("Content-Type: application/json");
            $result = array(
                'action' => $pxCtx->getBlockAction(),
                'uuid' => $block_uuid,
                'vid' => $pxCtx->getVid(),
                'appId' => $this->pxConfig['app_id'],
                'page' => base64_encode($html),
                'collectorUrl' => $collectorUrl
            );
            if ($this->pxConfig['return_response']) {
                return $result;
            }
            echo json_encode($result);
        }
        die();
    }

    /*
     * Method for assembling the Captcha script tag source
     */
    private function getCaptchaScript($pxConfig, $pxCtx) {
        $isMobile = ($pxCtx->getCookieOrigin() == 'header') ? "1" : "0";
        return "{$pxConfig['captcha_script_host']}/{$pxConfig['app_id']}/captcha.js?a={$pxCtx->getResponseBlockAction()}&u={$pxCtx->getUuid()}&v={$pxCtx->getVid()}&m=$isMobile";
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
            $validator = new PerimeterxCookieValidator($pxCtx, $this->pxConfig);
            $validator->verify();

            $client = new PerimeterxResetClient($pxCtx, $this->pxConfig);
            $client->sendResetRequest();
        } catch (\Exception $e) {
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

    /**
     * @return PerimeterxFieldExtractorManager
     */

     private function createFieldExtractorManager() {
        if (empty($this->pxConfig['px_enable_login_creds_extraction']) || empty($this->pxConfig['px_login_creds_extraction'])) {
            return null;
        }
        $extractorMap = PerimeterxFieldExtractorManager::createExtractorMap($this->pxConfig['px_login_creds_extraction']);
        return new PerimeterxFieldExtractorManager($extractorMap, $this->pxConfig['logger']);
     }
}
