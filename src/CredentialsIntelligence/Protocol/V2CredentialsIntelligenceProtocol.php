<?php

namespace Perimeterx\CredentialsIntelligence\Protocol;

use Perimeterx\PerimeterxUtils;
use Perimeterx\CredentialsIntelligence\LoginCredentialsFields;


class V2CredentialsIntelligenceProtocol implements ICredentialsIntelligenceProtocol {
    public function ProcessCredentials($username, $password) {
        $normalizedUsername = PerimeterxUtils::isEmailAddress($username) ? $this->normalizeEmailAddress($username) : $username;
        $hashedUsername = PerimeterxUtils::sha256($normalizedUsername);
        return new LoginCredentialsFields(
            $hashedUsername,
            $this->hashPassword($hashedUsername, $password),
            CIVersion::V2,
            $username
        );
    }

    private function normalizeEmailAddress($emailAddress) {
        $lowercaseEmail = strtolower($emailAddress);
        $atIndex = strpos($lowercaseEmail, "@");
        $domain = substr($lowercaseEmail, $atIndex);
        $normalizedUsername = substr($lowercaseEmail, 0, $atIndex);

        $plusIndex = strpos($normalizedUsername, "+");
        if ($plusIndex !== false) {
            $normalizedUsername = substr($normalizedUsername, 0, $plusIndex);
        }

        if ($domain === "@gmail.com") {
            $normalizedUsername = str_replace(".", "", $normalizedUsername);
        }

        return $normalizedUsername . $domain;
    }

    private function hashPassword($salt, $password) {
        $hashedPassword = PerimeterxUtils::sha256($password);
        return PerimeterxUtils::sha256($salt . $hashedPassword);
    }
}