<?php

namespace Perimeterx\CredentialsIntelligence\LoginSuccess;

class HeaderLoginSuccessfulParser implements ILoginSuccessfulParser {
    const DEFAULT_LOGIN_SUCCESSFUL_HEADER_NAME = "x-px-login-successful";
    const DEFAULT_LOGIN_SUCCESSFUL_HEADER_VALUE = "1";

    private $headerName;
    private $headerValue;
    
    /**
     * @param array $config
     */
    public function __construct($config) {
        $this->headerName = $this->getValue($config['px_login_successful_header_name'], HeaderLoginSuccessfulParser::DEFAULT_LOGIN_SUCCESSFUL_HEADER_NAME);
        $this->headerValue = $this->getValue($config['px_login_successful_header_value'], HeaderLoginSuccessfulParser::DEFAULT_LOGIN_SUCCESSFUL_HEADER_VALUE);
    }

    public function IsLoginSuccessful() {
        $responseHeaders = array();
        foreach (headers_list() as $header) {
            $headerKeyValue = explode(': ', $header);
            $responseHeaders[strtolower($headerKeyValue[0])] = $headerKeyValue[1];
        }

        return $responseHeaders[$this->headerName] && $responseHeaders[$this->headerName] === $this->headerValue;
    }

    /**
     * @param string $configValue
     * @param string $defaultValue
     * @return string
     */
    private function getValue($configValue, $defaultValue) {
        return empty($configValue) ? $defaultValue : strtolower($configValue);
    }
}