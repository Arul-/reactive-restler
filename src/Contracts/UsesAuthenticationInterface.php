<?php namespace Luracast\Restler\Contracts;

interface UsesAuthenticationInterface
{
    public function __setAuthenticationStatus(bool $isAuthenticated = false);
}