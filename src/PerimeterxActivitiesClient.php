<?php

namespace Perimeterx;


class PerimeterxActivitiesClient
{
    /**
     * @var object - perimeterx configuration object
     */
    private $pxConfig;

    /**
     * @var PerimeterxHttpClient
     */
    private $httpClient;

    /**
     * @param array $pxConfig - perimeterx configurations
     */
    public function __construct($pxConfig)
    {
        $this->pxConfig = $pxConfig;
        $this->httpClient = $pxConfig['http_client'];
    }

    /**
     * @param PerimeterxContext $pxCtx
     * @return array
     */
    private function filterSensitiveHeaders($pxCtx)
    {
        $retval = [];
        foreach ($pxCtx->getHeaders() as $key => $value) {
            if (isset($key, $value) and !in_array($key, $this->pxConfig['sensitive_headers'])) {
                $retval[$key] = $value;
            }
        }
        return $retval;
    }

    /**
     * @param $activityType
     * @param PerimeterxContext $pxCtx
     * @param $details
     */
    public function sendToPerimeterx($activityType, $pxCtx, $details = [])
    {
        if ($activityType == 'page_requested' and !$this->pxConfig['send_page_activities']) {
            return;
        }
        if ($activityType == 'block' and !$this->pxConfig['send_block_activities']) {
            return;
        }

        if ($this->pxConfig['module_mode'] != Perimeterx::$ACTIVE_MODE) {
            return;
        }

        $pxData = [];
        $pxData['type'] = $activityType;
        $pxData['headers'] = $this->filterSensitiveHeaders($pxCtx);
        $pxData['timestamp'] = time();
        $pxData['socket_ip'] = $pxCtx->getIp();
        $pxData['px_app_id'] = $this->pxConfig['app_id'];
        $pxData['url'] = $pxCtx->getFullUrl();
        $pxData['details'] = $details;
        $vid = $pxCtx->getVid();

        if (isset($vid)) {
            $pxData['vid'] = $vid;
        }

        $activities = [ $pxData ];

        $headers = [
            'Authorization' => 'Bearer ' . $this->pxConfig['auth_token'],
            'Content-Type' => 'application/json'
        ];
        $this->httpClient->send('/api/v1/collector/s2s', 'POST', $activities, $headers, $this->pxConfig['api_timeout'], $this->pxConfig['api_connect_timeout']);
    }
}
