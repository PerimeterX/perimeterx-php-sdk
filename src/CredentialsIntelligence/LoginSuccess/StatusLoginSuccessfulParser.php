<?php

namespace Perimeterx\CredentialsIntelligence\LoginSuccess;

class StatusLoginSuccessfulParser implements ILoginSuccessfulParser {
    /**
     * @var array
     */
    private $successfulStatuses;
    
    /**
     * @param array $config
     */
    public function __construct($config) {
        $this->successfulStatuses = is_array($config['px_login_successful_status']) ? 
            $config['px_login_successful_status'] : [$config['px_login_successful_status']];
    }

    public function IsLoginSuccessful() {
        return in_array(http_response_code(), $this->successfulStatuses);
    }
}