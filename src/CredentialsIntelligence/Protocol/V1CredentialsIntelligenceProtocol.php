<?php

namespace Perimeterx\CredentialsIntelligence\Protocol;

use Perimeterx\PerimeterxUtils;
use Perimeterx\CredentialsIntelligence\LoginCredentialsFields;

class V1CredentialsIntelligenceProtocol implements ICredentialsIntelligenceProtocol {
    public function ProcessCredentials($username, $password) {
        return new LoginCredentialsFields(
            PerimeterxUtils::sha256($username),
            PerimeterxUtils::sha256($password),
            CIVersion::V1,
            $username
        );
    }
}