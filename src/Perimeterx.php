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
                'sdk_name' => 'PHP SDK v2.0.0',
                'debug_mode' => false,
                'module_mode' => Perimeterx::$ACTIVE_MODE,
                'api_timeout' => 1,
                'api_connect_timeout' => 1,
                'perimeterx_server_host' => 'https://sapi.perimeterx.net',
                'local_proxy' => false
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
            $this->pxConfig['logger']->error('Uncaught exception while verifying perimeterx score' . $e->getCode() . ' ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * @param PerimeterxContext $pxCtx
     * @return bool - a true value when user is scored ok/blocking is disabled
     */
    private function handleVerification($pxCtx)
    {
        $score = $pxCtx->getScore();
        if (isset($score) and $score >= $this->pxConfig['blocking_score']) {
            $this->pxActivitiesClient->sendToPerimeterx('block', $pxCtx, ['block_uuid' => $pxCtx->getUuid(), 'block_score' => $pxCtx->getScore(), 'block_reason' => $pxCtx->getBlockReason(), 'module_version' => $this->pxConfig['sdk_name']]);
            if (isset($this->pxConfig['custom_block_handler'])) {
                $this->pxConfig['custom_block_handler']($pxCtx);
            } elseif (function_exists('pxCustomBlockHandler')) {
                call_user_func('pxCustomBlockHandler', $pxCtx);
            } elseif ($this->pxConfig['module_mode'] == Perimeterx::$ACTIVE_MODE) {
                $block_uuid = $pxCtx->getUuid();
                if ($this->pxConfig['captcha_enabled']) {
                    $html = '<html lang="en"> <head> <link type="text/css" rel="stylesheet" media="screen, print" href="//fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,700italic,800italic,400,300,600,700,800"> <meta charset="UTF-8"> <title>Access to This Page Has Been Blocked</title> <style> p { width: 60%; margin: 0 auto; font-size: 35px; } body { background-color: #a2a2a2; font-family: "Open Sans"; margin: 5%; } img { width: 180px; } a { color: #2020B1; text-decoration: blink; } a:hover { color: #2b60c6; } </style> <script src="https://www.google.com/recaptcha/api.js"></script> <script> window.px_vid = "' . $pxCtx->getVid() . '" ; function handleCaptcha(response) { var name = \'_pxCaptcha\'; var expiryUtc = new Date(Date.now() + 1000 * 10).toUTCString(); var cookieParts = [name, \'=\', response + \':\' + window.px_vid, \'; expires=\', expiryUtc, \'; path=/\']; document.cookie = cookieParts.join(\'\'); location.reload(); } </script> </head> <body cz-shortcut-listen="true"> <div><img src="http://storage.googleapis.com/instapage-thumbnails/035ca0ab/e94de863/1460594818-1523851-467x110-perimeterx.png"> </div> <span style="color: white; font-size: 34px;">Access to This Page Has Been Blocked</span> <div style="font-size: 24px;color: #000042;"><br> Access is blocked according to the site security policy.<br> Your browsing behaviour fingerprinting made us think you may be a bot. <br> <br> This may happen as a result ofthe following: <ul> <li>JavaScript is disabled or not running properly.</li> <li>Your browsing behaviour fingerprinting are not likely to be a regular user.</li> </ul> To read more about the bot defender solution: <a href="https://www.perimeterx.com/bot-defender">https://www.perimeterx.com/bot-defender</a><br> If you think the blocking was done by mistake, contact the site administrator. <br> <div class="g-recaptcha" data-sitekey="6Lcj-R8TAAAAABs3FrRPuQhLMbp5QrHsHufzLf7b" data-callback="handleCaptcha" data-theme="dark"></div> <br><span style="font-size: 20px;">Block Reference: <span style="color: #525151;">#' . $pxCtx->getUuid() . '</span></span> </div> </body> </html>';
                } else {
                    $html = '<html lang="en"><head><link type="text/css" rel="stylesheet" media="screen, print" href="//fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,700italic,800italic,400,300,600,700,800"><meta charset="UTF-8"><title>Access to This Page Has Been Blocked</title><style> p {width: 60%;margin: 0 auto;font-size: 35px;}body {background-color: #a2a2a2;font-family: "Open Sans";margin: 5%;} img {width: 180px;}a {color: #2020B1;text-decoration: blink;}a:hover {color: #2b60c6;} </style><style type="text/css"></style></head><body cz-shortcut-listen="true"><div><img src="http://storage.googleapis.com/instapage-thumbnails/035ca0ab/e94de863/1460594818-1523851-467x110-perimeterx.png"></div><span style="color: white; font-size: 34px;">Access to This Page Has Been Blocked</span><div style="font-size: 24px;color: #000042;"><br> Access is blocked according to the site security policy.<br> Your browsing behaviour fingerprinting made us think you may be a bot. <br> <br> This may happen as a result ofthe following:<ul><li>JavaScript is disabled or not running properly.</li><li>Your browsing behaviour fingerprinting are not likely to be a regular user.</li></ul>To read more about the bot defender solution: <a href="https://www.perimeterx.com/bot-defender">https://www.perimeterx.com/bot-defender</a><br> If you think the blocking was done by mistake, contact the site administrator. <br> <br><span style="font-size: 20px;">Block Reference: <span style="color: #525151;">#' . $block_uuid . '</span></span></div></body></html>';
                }
                header("Status: 403");
                header("Content-Type: text/html");
                echo $html;
                die();
            }
        } else {
            $this->pxActivitiesClient->sendToPerimeterx('page_requested', $pxCtx, ['module_version' => $this->pxConfig['sdk_name'], 'http_version' => $pxCtx->getHttpVersion(), 'http_method' => $pxCtx->getHttpMethod()]);
            return 1;
        }
    }
}
