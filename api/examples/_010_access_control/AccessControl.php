<?php

use Luracast\Restler\Contracts\AuthenticationInterface;
use Psr\Http\Message\ServerRequestInterface;
use \Luracast\Restler\Defaults;

class AccessControl implements AuthenticationInterface
{
    public static $requires = 'user';
    public static $role = 'user';

    public function __isAllowed(ServerRequestInterface $request): bool
    {
        //hardcoded api_key=>role for brevity
        $roles = array('12345' => 'user', '67890' => 'admin');
        $userClass = Defaults::$userIdentifierClass;

        if (!$api_key = $request->getQueryParams()['api_key'] ?? false) {
            return false;
        }
        if (!$role = $roles[$api_key] ?? false) {
            $userClass::setCacheIdentifier($api_key);
            return false;
        }
        $userClass::setCacheIdentifier($role);
        static::$role = $role;
        Defaults::$accessControlFunction = 'AccessControl::verifyAccess';
        return static::$requires == $role || $role == 'admin';
    }

    public static function __getWWWAuthenticateString(): string
    {
        return 'Query name="api_key"';
    }

    /**
     * @access private
     */
    public static function verifyAccess(array $m)
    {
        $requires =
            isset($m['class']['AccessControl']['properties']['requires'])
                ? $m['class']['AccessControl']['properties']['requires']
                : false;
        return $requires
            ? static::$role == 'admin' || static::$role == $requires
            : true;
    }

}
