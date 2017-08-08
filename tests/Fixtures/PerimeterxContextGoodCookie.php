<?php
namespace Perimeterx\Tests\Fixtures;

use Perimeterx\PerimeterxContext;
use GuzzleHttp\Client;

class PerimeterxContextGoodCookie extends PerimeterxContext
{

    /**
     * PerimeterxContextGoodCookie constructor.
     */
    public function __construct()
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.33 Safari/537.36';
        $url = 'http://www.perimeterx.com/';
        $this->headers = [
            ['name' => 'user-agent', 'value' => $ua],
            ['name' => 'origin', 'value' => 'http://www.perimeterx.com/']
        ];
        $this->hostname = 'perimeterx.com';
        $this->userAgent = $ua;
        $this->uri = '/';
        $this->full_url = $url;
        $this->ip = PX_LOCAL_IP_ADDR;
        $this->px_cookie = $this->fetchCookie($ua);
    }

    private function fetchCookie($ua)
    {
        $httpclient = new Client(['base_uri' => PX_SERVER_URL]);
        $headers = [
            'Origin' => 'http://www.perimeterx.com/',
            'User-Agent' => $ua,
            'Content-type' => 'application/x-www-form-urlencoded'
        ];

        $rawResponse = $httpclient->request('POST', '/api/v1/collector', ['body' => PX_ACTIVITY_PAYLOAD, 'headers' => $headers]);
        $response = json_decode($rawResponse->getBody());
        $px_cookie = explode("|", $response->do[1])[3];
        return $px_cookie;
    }

    public function getPxCookie(){
        return $this->px_cookie;
    }

    public function getPxCookies(){
        return ["V1" => $this->getPxCookie() ];
    }

    public function getUserAgent(){
        return $this->userAgent;
    }

    public function isSensitiveRoute(){
        return false;
    }

    public function getCookieOrigin(){
        return "cookie";
    }

    public function setDecodedCookie($val){}
    public function setScore($val){}
    public function setUuid($val){}
    public function setVid($val){}
    public function setBlockAction($val){}
    public function setCookieHmac($val){}
    public function setPassReason($val){}
}
?>
<script>

</script>