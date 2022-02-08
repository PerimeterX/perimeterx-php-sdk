<?php

namespace Perimeterx\CredentialsIntelligence\LoginSuccess;

class HeaderLoginSuccessfulParser implements ILoginSuccessfulParser {
    private $headerName;
    private $headerValue;
    
    /**
     * @param array $config
     */
    public function __construct($config) {
        $this->headerName = $config['px_login_successful_header_name'];
        $this->headerValue = $config['px_login_successful_header_value'];
    }

    public function IsLoginSuccessful() {
        $responseHeaders = headers_list();
        foreach ($responseHeaders as $header) {
            $headerKeyValue = explode(': ', $header);
            if (strtolower($headerKeyValue[0]) == $this->headerName) {
                return $this->headerValue == $headerKeyValue[1];
            }
        }
        return false;
    }
}