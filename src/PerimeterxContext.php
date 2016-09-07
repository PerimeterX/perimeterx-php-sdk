<?php

namespace Perimeterx;

class PerimeterxContext
{
    /**
     * @param $pxConfig array - perimeterx configurations
     */
    public function __construct($pxConfig)
    {
        if (isset($_SERVER['HTTP_COOKIE'])) {
            foreach (explode('; ', $_SERVER['HTTP_COOKIE']) as $rawcookie) {
                list($k, $v) = explode('=', $rawcookie, 2);
                if ($k == '_px') {
                    $this->px_cookie = $v;
                }
                if ($k == '_pxCaptcha') {
                    $this->px_captcha = $v;
                }
            }
        }

        $this->start_time = microtime(true);
        if (function_exists('getallheaders')) {
            $this->headers = getallheaders();
        } else {
            $this->headers = [];
        }

        $this->hostname = $_SERVER['SERVER_NAME'];
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
        $this->uri = $_SERVER['REQUEST_URI'];
        $this->full_url = $this->selfURL();
        $this->score = 0;

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
    }

    /**
     * @var string perimeterx risk cookie.
     */
    protected $px_cookie;

    /**
     * @var string perimeterx risk cookie.
     */
    protected $decoded_px_cookie;

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
     * @var string user's score.
     */
    protected $uuid;

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
        return $this->vid;
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
        //echo 'set call reason';
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
    public function getPxCookie()
    {
        return $this->px_cookie;
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

    private function selfURL()
    {
        $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
        $l = strtolower($_SERVER["SERVER_PROTOCOL"]);
        $protocol = substr($l, 0, strpos($l, "/")) . $s;
        $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":" . $_SERVER["SERVER_PORT"]);
        return $protocol . "://" . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
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

}
