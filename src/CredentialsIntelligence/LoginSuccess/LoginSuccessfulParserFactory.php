<?php

namespace Perimeterx\CredentialsIntelligence\LoginSuccess;

class LoginSuccessfulParserFactory {
    /**
     * @param array $config
     * @return ILoginSuccessfulParser
     */
    public static function Create($config) {
        switch ($config['px_login_successful_reporting_method']) {
            case LoginSuccessfulReportingMethod::STATUS:
                return new StatusLoginSuccessfulParser($config);
            case LoginSuccessfulReportingMethod::HEADER:
                return new HeaderLoginSuccessfulParser($config);
            case LoginSuccessfulReportingMethod::CUSTOM:
                return new CustomLoginSuccessfulParser($config);
            default:
                $config['logger']->error('No px_login_successful_reporting_method with value ' . $config['px_login_successful_reporting_method'] . ' found');
                return null;
        }
    }
}