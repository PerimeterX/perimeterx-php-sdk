<?php

namespace Perimeterx\CredentialsIntelligence\LoginSuccess;

class CustomLoginSuccessfulParser implements ILoginSuccessfulParser {
    /**
     * @var string
     */
    private $callbackName;
    
    /**
     * @param array $config
     */
    public function __construct($config) {
        $this->logger = $config['logger'];
        $this->callbackName = $config['px_login_successful_custom_callback'];
    }

    public function IsLoginSuccessful() {
        if (empty($this->callbackName) || !is_callable($this->callbackName)) {
            return false;
        }
        try {
            return call_user_func($this->callbackName);
        } catch (\Exception $e) {
            $this->logger->debug("Exception while calling custom login successful callback: " . $e->getMessage());
            return false;
        } catch (\Error $e) {
            $this->logger->debug("Error while calling custom login successful callback: " . $e->getMessage());
            return false;
        }
    }
}