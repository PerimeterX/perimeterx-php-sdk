<?php

namespace Perimeterx\CredentialsIntelligence\LoginSuccess;

interface ILoginSuccessfulParser {
    /**
     * @return boolean
     */
    public function IsLoginSuccessful();
}