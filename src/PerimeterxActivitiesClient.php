<?php

namespace Perimeterx;


class PerimeterxActivitiesClient
{
    /**
     * @var string
     */
    private $pxAuthToken;

    /**
     * @var object - perimeterx configuration object
     */
    private $pxConfig;

    /**
     * @var PerimeterxHttpClient
     */
    private $httpClient;

    /**
     * @var object
     */
    private $activities;

    /**
     * @param PerimeterxContext - perimeterx context
     * @param array - perimeterx configurations
     */
    public function __construct($pxConfig)
    {
        $this->pxConfig = $pxConfig;
        $this->pxAuthToken = $pxConfig['auth_token'];
        $this->httpClient = $pxConfig['http_client'];
        $this->activities = [];
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
     * @param $details
     * @param PerimeterxContext $pxCtx
     */
    public function sendToPerimeterx($activityType, $pxCtx, $details = [])
    {
        if ($activityType == 'page_requested' and !$this->pxConfig['send_page_activities']) {
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
        
        array_push($this->activities, $pxData);
        $this->sendActivities();
    }

    public function sendActivities()
    {
        if (count($this->activities) >= $this->pxConfig['max_buffer_len']) {
            $tempActivities = array_merge(array(), $this->activities);
            $this->activities = array_splice($this->activities, count($tempActivities));
            $headers = [
                'Content-Type' => 'application/json'
            ];
            $this->httpClient->send('/api/v1/collector/s2s', 'POST', $tempActivities, $headers);
        }
    }
}
