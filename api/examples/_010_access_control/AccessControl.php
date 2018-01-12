<?php

use Luracast\Restler\App;
use Luracast\Restler\Contracts\AccessControlInterface;
use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\HttpException;
use Luracast\Restler\iIdentifyUser;
use Psr\Http\Message\ServerRequestInterface;

class AccessControl implements AccessControlInterface
{
    public static $requires = 'user';
    public static $role = 'user';

    /**
     * @param ServerRequestInterface $request
     * @param array $responseHeaders
     * @return bool
     * @throws HttpException
     */
    public function __isAllowed(ServerRequestInterface $request, array &$responseHeaders = []): bool
    {
        //hardcoded api_key=>role for brevity
        $roles = array('12345' => 'user', '67890' => 'admin');

        if (!$api_key = $request->getQueryParams()['api_key'] ?? false) {
            return false;
        }
        $userClass = App::getClass(iIdentifyUser::class);
        if (!$role = $roles[$api_key] ?? false) {
            $userClass::setCacheIdentifier($api_key);
            return false;
        }
        $userClass::setCacheIdentifier($role);
        static::$role = $role;
        return static::$requires == $role || $role == 'admin';
    }

    public static function __getWWWAuthenticateString(): string
    {
        return 'Query name="api_key"';
    }

    public static function __verifyAccess(ApiMethodInfo $info): bool
    {
        $requires = $info->metadata['class']['AccessControl']['properties']['requires'] ?? false;
        return $requires
            ? static::$role == 'admin' || static::$role == $requires
            : true;
    }
}
