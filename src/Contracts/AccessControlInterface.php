<?php namespace Luracast\Restler\Contracts;

use Luracast\Restler\Data\Route;

interface AccessControlInterface extends AuthenticationInterface
{
    public static function verifyAccess(Route $route): bool;
}