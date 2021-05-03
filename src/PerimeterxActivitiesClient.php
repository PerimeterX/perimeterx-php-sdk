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
     * @var PerimeterxUtils
     */
    protected $pxUtils;

    /**
     * @param array $pxConfig - perimeterx configurations
     */
    public function __construct($pxConfig)
    {
        $this->pxConfig = $pxConfig;
        $this->httpClient = $pxConfig['http_client'];
        $this->pxUtils = new PerimeterxUtils();
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
    public function prepareActivitiesRequest($activityType, $pxCtx, $details = [])
    {
        if (isset($this->pxConfig['additional_activity_handler'])) {
            $this->pxConfig['additional_activity_handler']($activityType, $pxCtx, $details);
        }

        if ($this->pxConfig['defer_activities']) {
            register_shutdown_function([$this, 'sendToPerimeterx'], $activityType, $pxCtx, $details);
        } else {
            $this->sendToPerimeterx($activityType, $pxCtx, $details);
        }
    }

    public function sendToPerimeterx($activityType, $pxCtx, $details) {
        $details['cookie_origin'] = $pxCtx->getCookieOrigin();
        $details['http_method'] = $pxCtx->getHttpMethod();

        $details['module_version'] = $this->pxConfig['sdk_name'];
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

        if ($pxCtx->getPxhdCookie() != null) {
            $pxData['pxhd'] = $pxCtx->getPxhdCookie();
        }

        if (isset($this->pxConfig['enrich_custom_params'])) {
            $this->pxUtils->handleCustomParams($this->pxConfig, $pxData['details']);
        }

        $activities = [$pxData];
        $headers = [
            'Authorization' => 'Bearer ' . $this->pxConfig['auth_token'],
            'Content-Type' => 'application/json'
        ];
        $this->httpClient->send('/api/v1/collector/s2s', 'POST', $activities, $headers, $this->pxConfig['activities_timeout'], $this->pxConfig['activities_connect_timeout']);
    }

    /**
     * @param PerimeterxContext $pxCtx
     */
    public function sendBlockActivity($pxCtx)
    {
        if (!$this->pxConfig['send_page_activities']) {
            return;
        }

        $details = [];
        $details['block_uuid'] = $pxCtx->getUuid();
        $details['block_score'] = $pxCtx->getScore();
        $details['block_reason'] = $pxCtx->getBlockReason();
        $details['block_action'] = $pxCtx->getResponseBlockAction();
        $details['risk_rtt'] = $pxCtx->getRiskRtt();
        $details['simulated_block'] = $this->pxConfig['module_mode'] == Perimeterx::$MONITOR_MODE;

        $this->prepareActivitiesRequest("block", $pxCtx, $details);
    }

    /**
     * @param PerimeterxContext $pxCtx
     */
    public function sendPageRequestedActivity($pxCtx)
    {
        if (!$this->pxConfig['send_page_activities']) {
            return;
        }

        $details = [];
        $details['client_uuid'] = $pxCtx->getUuid();
        $details['module_version'] = $this->pxConfig['sdk_name'];
        $details['http_version'] = $pxCtx->getHttpVersion();
        $details['pass_reason'] = $pxCtx->getPassReason();
        $details['risk_rtt'] = $pxCtx->getRiskRtt();

        if ($pxCtx->getDecodedCookie()) {
            $details['px_cookie'] = $pxCtx->getDecodedCookie();
        }

        if ($pxCtx->getCookieHmac()) {
            $details['px_cookie_hmac'] = $pxCtx->getCookieHmac();
        }

        $this->prepareActivitiesRequest('page_requested', $pxCtx, $details);
    }
}
