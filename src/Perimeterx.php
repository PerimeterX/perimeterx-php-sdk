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
                'encryption_enabled' => true,
                'blocking_score' => 70,
                'sensitive_headers' => ['cookie', 'cookies'],
                'max_buffer_len' => 1,
                'send_page_activities' => false,
                'send_block_activities' => true,
                'sdk_name' => 'PHP SDK v2.2.3',
                'debug_mode' => false,
                'perimeterx_server_host' => 'https://sapi.perimeterx.net',
                'module_mode' => Perimeterx::$ACTIVE_MODE,
                'api_timeout' => 1,
                'api_connect_timeout' => 1,
                'local_proxy' => false,
            ], $pxConfig);

            if (empty($this->pxConfig['logger'])) {
                $this->pxConfig['logger'] = new PerimeterxLogger();
            }

            $this->pxConfig['perimeterx_server_host'] = 'https://sapi-' . strtolower($this->pxConfig['app_id']) . '.perimeterx.net';

            $httpClient = new PerimeterxHttpClient($this->pxConfig);
            $this->pxConfig['http_client'] = $httpClient;
            $this->pxActivitiesClient = new PerimeterxActivitiesClient($this->pxConfig);
        } catch (\Exception $e) {
            throw new PerimeterxException('Uncaught exception ' . $e->getCode() . ' ' . $e->getMessage());
        }
    }


    public function pxVerify()
    {
        try {
            if (!$this->pxConfig['module_enabled']) {
                return 1;
            }

            $pxCtx = new PerimeterxContext($this->pxConfig);
            $captchaValidator = new PerimeterxCaptchaValidator($pxCtx, $this->pxConfig);
            if ($captchaValidator->verify()) {
                return $this->handleVerification($pxCtx);
            };

            $cookieValidator = new PerimeterxCookieValidator($pxCtx, $this->pxConfig);
            if (!$cookieValidator->verify()) {
                $s2sValidator = new PerimeterxS2SValidator($pxCtx, $this->pxConfig);
                $s2sValidator->verify();
            };
            return $this->handleVerification($pxCtx);
        } catch (\Exception $e) {
            $this->pxConfig['logger']->error('Uncaught exception while verifying perimeterx score ' . $e->getCode() . ' ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * @param PerimeterxContext $pxCtx
     * @return bool - a true value when user is scored ok/blocking is disabled
     */
    private function handleVerification($pxCtx)
    {
        $mustache = new \Mustache_Engine(array(
          'loader' => new \Mustache_Loader_FilesystemLoader(dirname(__FILE__) . '/templates'),
        ));
        $score = $pxCtx->getScore();
        if (isset($score) and $score >= $this->pxConfig['blocking_score']) {
            $this->pxActivitiesClient->sendToPerimeterx('block', $pxCtx, ['block_uuid' => $pxCtx->getUuid(), 'block_score' => $pxCtx->getScore(), 'block_reason' => $pxCtx->getBlockReason(), 'module_version' => $this->pxConfig['sdk_name']]);
            if (isset($this->pxConfig['custom_block_handler'])) {
                $this->pxConfig['custom_block_handler']($pxCtx);
            } elseif (function_exists('pxCustomBlockHandler')) {
                call_user_func('pxCustomBlockHandler', $pxCtx);
            } elseif ($this->pxConfig['module_mode'] == Perimeterx::$ACTIVE_MODE) {
                $block_uuid = $pxCtx->getUuid();

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

                if ($this->pxConfig['captcha_enabled']) {
                    $html = $mustache->render('captcha',$templateInputs);
                } else {
                    $html = $mustache->render('block',$templateInputs);
                }
                header("Status: 403");
                header("Content-Type: text/html");
                echo $html;
                die();
            }
        } else {
            $details = ['module_version' => $this->pxConfig['sdk_name'], 'http_version' => $pxCtx->getHttpVersion(), 'http_method' => $pxCtx->getHttpMethod()];
            if ($pxCtx->getDecodedCookie()) {
                $details['px_cookie'] = $pxCtx->getDecodedCookie();
            }
            $this->pxActivitiesClient->sendToPerimeterx('page_requested', $pxCtx, $details);
            return 1;
        }
    }

    /**
    * Method for retreving jsRef from pxConfig if exists
    */
    private function getJsRef()
    {
      $jsRefScript = '';
      if (isset($this->pxConfig['js_ref'])){
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
      if (isset($this->pxConfig['css_ref'])){
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
            $cookie = new PerimeterxCookie($pxCtx, $this->pxConfig);
            if ($cookie->isValid()) {
                $pxCtx->setVid($cookie->getVid());
                $pxCtx->setUuid($cookie->getUuid());
            }

            $client = new PerimeterxResetClient($pxCtx, $this->pxConfig);
            $client->sendResetRequest();
        } catch (\Exception $e) {
            $this->pxConfig['logger']->error('Uncaught exception while resetting perimeterx score' . $e->getCode() . ' ' . $e->getMessage());

            return 1;
        }
    }

    /**
     * @return string
     */
    public function getPxConfig()
    {
        return $this->pxConfig;
    }
}
