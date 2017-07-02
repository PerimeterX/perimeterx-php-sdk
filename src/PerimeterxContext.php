<?php

namespace Perimeterx;

class PerimeterxContext
{
    public static $MOBILE_SDK_HEADER = "X-PX-AUTHORIZATION";
    /**
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxConfig)
    {

        $this->cookie_origin = "cookie";

        if (isset($_SERVER['HTTP_COOKIE'])) {
            foreach (explode('; ', $_SERVER['HTTP_COOKIE']) as $rawcookie) {
                if (!empty($rawcookie) && strpos($rawcookie, '=') !== false) {
                    $this->explodeCookieToVersion('=', $rawcookie);
                }
            }
        }

        $this->start_time = microtime(true);
        if (function_exists('getallheaders')) {
            $this->headers = getallheaders();
        } else {
            $this->headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $this->headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }

        if (isset($this->headers[PerimeterxContext::$MOBILE_SDK_HEADER])) {
            $this->cookie_origin = "header";
            $this->explodeCookieToVersion(':', $this->headers[PerimeterxContext::$MOBILE_SDK_HEADER]);
        }

        $this->hostname = $_SERVER['HTTP_HOST'];
        // User Agent isn't always sent by bots so handle it gracefully.
        $this->userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if (isset($pxConfig['custom_uri'])) {
            $this->uri = $pxConfig['custom_uri']($this);
        } else {
            $this->uri = $_SERVER['REQUEST_URI'];
        }
        $this->full_url = $this->selfURL();
        $this->score = 0;
        $this->risk_rtt = 0;

        if (isset($pxConfig['custom_user_ip'])) {
            $this->ip = $pxConfig['custom_user_ip']($this);
        } elseif (function_exists('pxCustomUserIP')) {
            call_user_func('pxCustomUserIP', $this);
        } else {
            $this->ip = $_SERVER['REMOTE_ADDR'];
        }

        if (isset($_SERVER['SERVER_PROTOCOL'])) {
            $httpVer = explode("/", $_SERVER['SERVER_PROTOCOL']);
            if (isset($httpVer[1])) {
                $this->http_version = $httpVer[1];
            }
        }
        $this->http_method = $_SERVER['REQUEST_METHOD'];
        $this->sensitive_route = $this->checkSensitiveRoutePrefix($pxConfig['sensitive_routes'], $this->uri);
    }

    /**
     * @var string perimeterx cookie origin
     */
    protected $cookie_origin;

    /**
     * @var string perimeterx risk cookie.
     */
    protected $px_cookies;

    /**
     * @var string perimeterx risk cookie.
     */
    protected $decoded_px_cookie;

    /**
     * @var string cookie hmac
     */
    protected $px_cookie_hmac;


    /**
     * @var string perimeterx captcha cookie.
     */
    protected $px_captcha;

    /**
     * @var string user's ip.
     */
    protected $ip;

    /**
     * @var string current request http version.
     */
    protected $http_version;

    /**
     * @var string current request http version.
     */
    protected $http_method;

    /**
     * @var array request headers.
     */
    protected $headers;

    /**
     * @var string request hostname.
     */
    protected $hostname;

    /**
     * @var string request uri.
     */
    protected $uri;

    /**
     * @var string user's user agent.
     */
    protected $userAgent;


    /**
     * @var string request full url.
     */
    protected $full_url;

    /**
     * @var string request full url.
     */
    public $start_time;

    /**
     * @var string server2server call reason - get populated if cookie verification fails.
     */
    protected $s2s_call_reason;

    /**
     * @var string user's score.
     */
    protected $score;


    /**
     * @var string user's visitor id.
     */
    protected $vid;

    /**
     * @var string block reason - get populated when user cross score
     */
    protected $block_reason;

    /**
     * @var string pass reason - describes why a page_requested activity sent
     */
    protected $pass_reason;

    /**
     * @var string pass reason - describes why a page_requested activity sent
     */
    protected $risk_rtt;

    /**
     * @var string user's score.
     */
    protected $uuid;

    /**
     * @var bool true if request was sent to S2S risk api
     */
    protected $is_made_s2s_api_call;

    /**
     * @var string S2S api call HTTP error message
     */
    protected $s2s_http_error_msg;

    /**
     * @var string block action
     */
    protected $block_action;

    /**
     * @var string block data
     */
    protected $block_data;

    /**
     * @return string
     */
    public function getVid()
    {
        return $this->vid;
    }

    /**
     * @return string
     */
    public function getBlockReason()
    {
        return $this->block_reason;
    }

    /**
     * @param string $ip
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    /**
     * @param string $vid
     */
    public function setVid($vid)
    {
        $this->vid = $vid;
    }

    /**
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @param string $is_made_s2s_api_call
     */
    public function setIsMadeS2SRiskApiCall($is_made_s2s_api_call)
    {
        $this->is_made_s2s_api_call = $is_made_s2s_api_call;
    }

    /**
     * @return string
     */
    public function getIsMadeS2SRiskApiCall()
    {
        return $this->is_made_s2s_api_call;
    }

    /**
     * @param string $s2s_http_error_msg
     */
    public function setS2SHttpErrorMsg($s2s_http_error_msg)
    {
        $this->s2s_http_error_msg = $s2s_http_error_msg;
    }

    /**
     * @return string
     */
    public function getS2SHttpErrorMsg()
    {
        return $this->s2s_http_error_msg;
    }

    /**
     * @param string $block_reason
     */
    public function setBlockReason($block_reason)
    {
        $this->block_reason = $block_reason;
    }

    /**
     * @return string
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * @param string $score
     */
    public function setScore($score)
    {
        $this->score = $score;
    }

    /**
     * @return string
     */
    public function getS2SCallReason()
    {
        return $this->s2s_call_reason;
    }

    /**
     * @param string $s2s_call_reason
     */
    public function setS2SCallReason($s2s_call_reason)
    {
        $this->s2s_call_reason = $s2s_call_reason;
    }

    /**
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * @return string
     */
    public function getCookieOrigin()
    {
        return $this->cookie_origin;
    }

    /**
     * @return string - v3 cookie if exists, if not - v1 cookie
     */
    public function getPxCookie()
    {
        return isset($this->px_cookies['v3']) ? $this->px_cookies['v3'] : $this->px_cookies['v1'];
    }

    /**
     * @return array of px cookies found on the request
     */
    public function getPxCookies()
    {
        return $this->px_cookies;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getHostname()
    {
        return $this->hostname;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @return string
     */
    public function getFullUrl()
    {
        return $this->full_url;
    }

    /**
     * @return string
     */
    public function getPxCaptcha()
    {
        return $this->px_captcha;
    }

    /**
     * @param string $px_captcha
     */
    public function setPxCaptcha($px_captcha)
    {
        $this->px_captcha = $px_captcha;
    }

    /**
     * @return string
     */
    public function getDecodedCookie()
    {
        return $this->decoded_px_cookie;
    }

    /**
     * @param string $cookie
     */
    public function setDecodedCookie($cookie)
    {
        $this->decoded_px_cookie = $cookie;
    }

    private function checkSensitiveRoutePrefix($sensitive_routes, $uri)
    {
        foreach ($sensitive_routes as $route) {
            if (strncmp($uri, $route, strlen($route)) === 0) {
                return true;
            }
        }
        return false;
    }

    private function selfURL()
    {
        $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
        $l = strtolower($_SERVER["SERVER_PROTOCOL"]);
        $protocol = substr($l, 0, strpos($l, "/")) . $s;
        $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":" . $_SERVER["SERVER_PORT"]);
        return $protocol . "://" . $_SERVER['HTTP_HOST'] . $port . $this->uri;
    }

    private function explodeCookieToVersion($delimiter, $cookie) {
        list($k, $v) = explode($delimiter, $cookie, 2);
        if ($k == '3' || $k == '_px3') {
            $this->px_cookies['v3'] = $v;
        }
        if ($k == '1' || $k == '_px') {
            $this->px_cookies['v1'] = $v;
        }
        if ($k == '_pxCaptcha') {
            $this->px_captcha = $v;
        }
    }

    /**
     * @return string
     */
    public function getHttpVersion()
    {
        return $this->http_version;
    }

    /**
     * @return string
     */
    public function getHttpMethod()
    {
        return $this->http_method;
    }

    /**
     * @return string
     */
    public function getBlockAction()
    {
        return $this->block_action;
    }

    public function setBlockAction($block_action)
    {
        switch ($block_action) {
            case 'c':
                $this->block_action = 'captcha';
                break;
            case 'b':
                $this->block_action = 'block';
                break;
            case 'j':
                $this->block_action = 'challenge';
                break;
            default:
                $this->block_action = 'captcha';
        }
    }

    public function setBlockActionData($block_data = '')
    {
        $this->block_data = $block_data;
    }


    public function getBlockActionData()
    {
        return $this->block_data;
    }

    public function setCookieHmac($hmac)
    {
        $this->px_cookie_hmac = $hmac;
    }

    public function getCookieHmac()
    {
        return $this->px_cookie_hmac;
    }

    public function isSensitiveRoute()
    {
        return $this->sensitive_route;
    }

    public function expose()
    {
        return get_object_vars($this);
    }

    /**
     * @param string
     */
    public function setPassReason($pass_reason)
    {
        $this->pass_reason = $pass_reason;
    }

    /**
     * @return string
     */
    public function getPassReason()
    {
        return $this->pass_reason;
    }

    /**
     * @param int
     */
    public function setRiskRtt($risk_rtt){
        $this->risk_rtt =  $risk_rtt;
    }

    /**
     * @return int
     */
    public function getRiskRtt(){
        return $this->risk_rtt;
    }
}
