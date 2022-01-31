<?php

namespace Perimeterx\CredentialsIntelligence\Protocol;

class CredentialsIntelligenceProtocolFactory {
    /**
     * @param string $version
     * @return ICredentialsIntelligenceProtocol
     */
    public static function Create($version) {
        switch($version) {
            case CIVersion::V1:
                return new V1CredentialsIntelligenceProtocol();
            case CIVersion::MULTISTEP_SSO:
                return new MultistepSSOCredentialsIntelligenceProtocol();
            default:
                return null;
        }
    }
}