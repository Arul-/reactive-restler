<?php namespace Luracast\Restler\Contracts;

use Luracast\Restler\Data\ApiMethodInfo;

interface AccessControlInterface extends AuthenticationInterface
{
    public static function __verifyAccess(ApiMethodInfo $info): bool;
}