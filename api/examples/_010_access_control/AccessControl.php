<?php

use Luracast\Restler\Defaults;
use Luracast\Restler\Contracts\AccessControlInterface;
use Luracast\Restler\Contracts\SelectivePathsInterface;
use Luracast\Restler\Contracts\SelectivePathsTrait;
use Luracast\Restler\Utils\ApiMethodInfo;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Contracts\UserIdentificationInterface;
use Luracast\Restler\Utils\ClassName;
use Psr\Http\Message\ServerRequestInterface;

class AccessControl implements AccessControlInterface, SelectivePathsInterface
{
    use SelectivePathsTrait;

    public static $requires = 'user';
    public static $role = 'user';

    /**
     * @param ServerRequestInterface $request
     * @param array $responseHeaders
     * @return bool
     * @throws HttpException
     */
    public function _isAllowed(ServerRequestInterface $request, array &$responseHeaders = []): bool
    {
        //hardcoded api_key=>role for brevity
        $roles = array('12345' => 'user', '67890' => 'admin');

        if (!$api_key = $request->getQueryParams()['api_key'] ?? false) {
            return false;
        }
        $userClass = ClassName::get(UserIdentificationInterface::class);
        if (!$role = $roles[$api_key] ?? false) {
            $userClass::setCacheIdentifier($api_key);
            return false;
        }
        $userClass::setCacheIdentifier($role);
        static::$role = $role;
        return static::$requires == $role || $role == 'admin';
    }

    public static function getWWWAuthenticateString(): string
    {
        return 'Query name="api_key"';
    }

    public static function verifyAccess(ApiMethodInfo $info): bool
    {
        $requires = $info->metadata['class']['AccessControl']['properties']['requires'] ?? false;
        return $requires
            ? static::$role == 'admin' || static::$role == $requires
            : true;
    }
}
