<?php


namespace Luracast\Restler\Auth;


use Luracast\Restler\Contracts\AccessControlInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\ResponseHeaders;
use Psr\Http\Message\ServerRequestInterface;

class JsonWebTokenAccessControl extends JsonWebToken implements AccessControlInterface
{
    public static $rolesAccessor = ['realm_access', 'roles'];
    public $requires = 'user';
    public $role = 'user';
    public $id = null;

    public function _isAllowed(ServerRequestInterface $request, ResponseHeaders $responseHeaders): bool
    {
        if (!parent::_isAllowed($request, $responseHeaders)) {
            return false;
        }
        $roles = $this->roles();
        if (!in_array($this->requires, $roles)) {
            $this->role = $roles[0];
            $this->accessDenied('Insufficient Access Rights');
        }
        $this->role = $this->requires;
        return true;
    }

    /**
     * @return array|null
     * @throws HttpException
     */
    private function roles(): array
    {
        $p = $this->token;
        foreach (static::$rolesAccessor as $property) {
            $p = $p->{$property} ?? null;
            if (!$p) {
                $this->accessDenied('Roles not specified');
            }
        }
        return $p;
    }
}
