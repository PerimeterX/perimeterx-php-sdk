<?php

namespace Perimeterx\CredentialsIntelligence\Protocol;

use Perimeterx\CredentialsIntelligence\LoginCredentialsFields;
use Perimeterx\PerimeterxUtils;

class MultistepSSOCredentialsIntelligenceProtocol implements ICredentialsIntelligenceProtocol {
    public function ProcessCredentials($username, $password) {
        $username = empty($username) ? null : $username;
        return new LoginCredentialsFields(
            $username,
            empty($password) ? null : PerimeterxUtils::sha256($password),
            CIVersion::MULTISTEP_SSO,
            $username
        );
    }
}