<?php

use Luracast\Restler\Contracts\AccessControlInterface;
use Luracast\Restler\Contracts\ExplorableAuthenticationInterface;
use Luracast\Restler\Contracts\SelectivePathsInterface;
use Luracast\Restler\Contracts\SelectivePathsTrait;
use Luracast\Restler\Contracts\UserIdentificationInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\OpenApi3\Security\ApiKeyAuth;
use Luracast\Restler\OpenApi3\Security\Scheme;
use Luracast\Restler\ResponseHeaders;
use Luracast\Restler\StaticProperties;
use Luracast\Restler\Utils\ClassName;
use Psr\Http\Message\ServerRequestInterface;

class AccessControl implements AccessControlInterface, SelectivePathsInterface, ExplorableAuthenticationInterface
{
    use SelectivePathsTrait;

    public static $super = true;
    public $requires = 'user';
    public $role = 'user';

    /**
     * @param ServerRequestInterface $request
     * @param ResponseHeaders $responseHeaders
     * @return bool
     * @throws HttpException 401
     */
    public function _isAllowed(ServerRequestInterface $request, ResponseHeaders $responseHeaders): bool
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
        $this->role = $role;
        return $this->requires == $role || $role == 'admin';
    }

    public static function getWWWAuthenticateString(): string
    {
        return 'Query name="api_key"';
    }

    public static function scheme(): Scheme
    {
        return new ApiKeyAuth('api_key', ApiKeyAuth::IN_QUERY);
    }
}
