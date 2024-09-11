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
        return PerimeterxUtils::filterSensitiveHeaders($pxCtx->getHeaders(), $this->pxConfig['sensitive_headers']);
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
        $activity = $this->generateActivity($activityType, $pxCtx, $details);
        $this->sendActivity($activity);
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
        $details['block_score'] = $pxCtx->getScore();
        $details['block_reason'] = $pxCtx->getBlockReason();
        $details['block_action'] = $pxCtx->getResponseBlockAction();
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
        $details['pass_reason'] = $pxCtx->getPassReason();

        if ($pxCtx->getPassReason() === "s2s_error") {
            $this->setS2SErrorInfo($details, $pxCtx);
        }

        if ($pxCtx->getDecodedCookie()) {
            $details['px_cookie'] = $pxCtx->getDecodedCookie();
        }

        if ($pxCtx->getCookieHmac()) {
            $details['px_cookie_hmac'] = $pxCtx->getCookieHmac();
        }

        $this->prepareActivitiesRequest('page_requested', $pxCtx, $details);
    }

    private function setS2SErrorInfo(&$details, &$pxCtx) {
        $details['s2s_error_reason'] = $pxCtx->getS2SErrorReason();
        $details['s2s_error_message'] = $pxCtx->getS2SErrorMessage();
        $details['s2s_error_http_status'] = $pxCtx->getS2SErrorHttpStatus();
        $details['s2s_error_http_message'] = $pxCtx->getS2SErrorHttpMessage();
    }

    /**
     * @param PerimeterxContext $pxCtx
     * @param array $details
     */
    private function addAdditionalFieldsToDetails(&$pxCtx, &$details) {
        $loginCredentials = $pxCtx->getLoginCredentials();
        if (!is_null($loginCredentials)) {
            $details['ci_version'] = $loginCredentials->getCIVersion();
            $details['credentials_compromised'] = $pxCtx->areCredentialsCompromised();
            if (!empty($loginCredentials->getSsoStep())) {
                $details['sso_step'] = $loginCredentials->getSsoStep();
            }
        }
        $graphqlFields = $pxCtx->getGraphqlFields();
        if (!is_null($graphqlFields)) {
            $details['graphql_operation_type'] = $graphqlFields->getOperationType();
            $details['graphql_operation_name'] = $graphqlFields->getOperationName();
        }
    }

    public function generateActivity($activityType, $pxCtx, $details) {
        $pxData = [];
        $pxData['type'] = $activityType;
        $pxData['timestamp'] = time();
        $pxData['socket_ip'] = $pxCtx->getIp();
        $pxData['px_app_id'] = $this->pxConfig['app_id'];
        $pxData['url'] = $pxCtx->getFullUrl();

        $vid = $pxCtx->getVid();
        if (isset($vid)) {
            $pxData['vid'] = $vid;
        }

        $details['client_uuid'] = $pxCtx->getUuid();
        $details['request_id'] = $pxCtx->getRequestId();
        $this->addAdditionalFieldsToDetails($pxCtx, $details);

        if ($activityType !== 'additional_s2s') {
            $pxData['headers'] = $this->filterSensitiveHeaders($pxCtx);

            $details['http_method'] = $pxCtx->getHttpMethod();
            $details['http_version'] = $pxCtx->getHttpVersion();
            $details['module_version'] = $this->pxConfig['sdk_name'];

            $cookieOrigin = $pxCtx->getCookieOrigin();
            if ($cookieOrigin != '') {
                $details['cookie_origin'] = $cookieOrigin;
            }

            $riskRtt = $pxCtx->getRiskRtt();
            if ($riskRtt != 0) {
                $details['risk_rtt'] = $riskRtt;
            }

            if ($pxCtx->getPxhdCookie() != null) {
                $pxData['pxhd'] = $pxCtx->getPxhdCookie();
            }

            if (isset($this->pxConfig['enrich_custom_params'])) {
                $this->pxUtils->handleCustomParams($this->pxConfig, $details);
            }
        }

        $pxData['details'] = $details;
        return $pxData;
    }

    public function sendActivity($activity) {
        $activities = [$activity];
        $headers = [
            'Authorization' => 'Bearer ' . $this->pxConfig['auth_token'],
            'Content-Type' => 'application/json'
        ];
        $this->httpClient->send(
            '/api/v1/collector/s2s',
            'POST',
            $activities,
            $headers,
            $this->pxConfig['activities_timeout'],
            $this->pxConfig['activities_connect_timeout']
        );
    }
}
