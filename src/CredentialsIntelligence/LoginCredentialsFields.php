<?php

namespace Perimeterx\CredentialsIntelligence;

class LoginCredentialsFields {

    private $user;
    private $pass;
    private $version;
    private $rawUsername;
    private $ssoStep;

    /**
     * @param string $user
     * @param string $pass
     * @param string $ciVersion
     * @param string $rawUsername
     */
    public function __construct($user, $pass, $ciVersion, $rawUsername) {
        $this->user = $user;
        $this->pass = $pass;
        $this->version = $ciVersion;
        $this->rawUsername = $rawUsername;
        $this->ssoStep = "";
        if ($this->version == Protocol\CIVersion::MULTISTEP_SSO) {
            if (!empty($user)) {
                $this->ssoStep = Protocol\SSOStep::USER;
            } else if (!empty($pass)) {
                $this->ssoStep = Protocol\SSOStep::PASS;
            }
        }
    }

    public function getUser() {
        return $this->user;
    }

    public function getPass() {
        return $this->pass;
    }

    public function getCIVersion() {
        return $this->version;
    }

    public function getRawUsername() {
        return $this->rawUsername;
    }

    public function getSsoStep() {
        return $this->ssoStep;
    }
}