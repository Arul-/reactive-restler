<?php namespace Luracast\Restler\Contracts;

use Luracast\Restler\Utils\ApiMethodInfo;

interface AccessControlInterface extends AuthenticationInterface
{
    public static function verifyAccess(ApiMethodInfo $info): bool;
}