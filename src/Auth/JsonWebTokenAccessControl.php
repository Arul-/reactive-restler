<?php


namespace Luracast\Restler\Auth;


use Luracast\Restler\Contracts\AccessControlInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\ResponseHeaders;
use Psr\Http\Message\ServerRequestInterface;

class JsonWebTokenAccessControl extends JsonWebToken implements AccessControlInterface
{
    public static $rolesAccessor = ['realm_access', 'roles'];
    public static $scopesAccessor = ['scope'];
    public static $permissionsAccessor = ['resource_access', 'account', 'roles'];
    //
    public $role = 'user';
    public $roleRequired = null;
    //
    public $scope = null;
    public $scopeRequired = null;
    //
    public $permission = null;
    public $permissionRequired = null;
    //
    public $id = null;

    public function _isAllowed(ServerRequestInterface $request, ResponseHeaders $responseHeaders): bool
    {
        if (!parent::_isAllowed($request, $responseHeaders)) {
            return false;
        }
        $this->check('role');
        $this->check('scope');
        $this->check('permission');

        return true;
    }

    /**
     * @param string $name
     * @throws HttpException
     */
    protected function check(string $name)
    {
        $p = $this->token;
        $expected = $this->{$name . 'Required'};
        if (!$expected) {
            return;
        }
        $accessor = static::${$name . 'sAccessor'};
        foreach ($accessor as $property) {
            $p = $p->{$property} ?? null;
            if (!$p) {
                $this->accessDenied(ucfirst($name) . ' not specified');
            }
        }
        if (is_array($p)) {
            if (in_array($expected, $p)) {
                $this->{$name} = $expected;
                return;
            }
            $this->{$name} = $p[0];
        } elseif (is_string($p)) {
            if (false !== strpos($p, $expected)) {
                $this->{$name} = $expected;
                return;
            }
        }
        $this->accessDenied('Insufficient ' . ucfirst($name) . '. `' . $expected . '` ' . $name . ' is required.');
    }
}
