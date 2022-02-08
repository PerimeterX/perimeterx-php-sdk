<?php

namespace Perimeterx\CredentialsIntelligence\Protocol;

interface ICredentialsIntelligenceProtocol {
    /**
     * @param string $username
     * @param string $password
     * @return LoginCredentialsFields
     */
    public function ProcessCredentials($username, $password);
}