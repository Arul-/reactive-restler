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

    public $requires = 'user';
    public $role = 'user';
    public $id = null;

    /** @var string[][] hardcoded to string[password]=>[id,role] */
    private static $users = [
        '123' => ['a', 'user'],
        '456' => ['b', 'user'],
        '789' => ['c', 'admin']
    ];

    /**
     * @param string $owner
     * @return bool
     * @throws HttpException
     */
    public function _verifyPermissionForDocumentOwnedBy(string $owner): bool
    {
        if ('admin' === $this->role) return true;
        if ($owner === $this->id) return true;
        throw new HttpException(403, 'permission denied.');
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseHeaders $responseHeaders
     * @return bool
     * @throws HttpException 401
     */
    public function _isAllowed(ServerRequestInterface $request, ResponseHeaders $responseHeaders): bool
    {
        if (!$api_key = $request->getQueryParams()['api_key'] ?? false) {
            return false;
        }
        /** @var UserIdentificationInterface $userClass */
        $userClass = ClassName::get(UserIdentificationInterface::class);
        if (!$user = self::$users[$api_key] ?? null) {
            $userClass::setCacheIdentifier($api_key);
            return false;
        }
        [$id, $role] = $user;
        $userClass::setCacheIdentifier($id);
        $this->role = $role;
        $this->id = $id;
        //Role-based access control (RBAC)
        return $role === 'admin' || $role === $this->requires;
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
