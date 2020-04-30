<?php

use Luracast\Restler\Exceptions\HttpException;

class Access
{

    /**
     * @var AccessControl
     */
    private $accessControl;

    public function __construct(AccessControl $accessControl)
    {
        $this->accessControl = $accessControl;
    }

    public function all()
    {
        return "public api, all are welcome";
    }

    /**
     * @access protected
     * @class  AccessControl {@requires user}
     */
    public function user()
    {
        return "protected api, only user and admin can access";
    }

    /**
     * @access protected
     * @class  AccessControl {@requires user}
     * @param int $id id of the document
     *
     * @return string
     * @throws HttpException 403 permission denied
     * @throws HttpException 404 document not found
     */
    public function documents(int $id)
    {
        $documents = [1 => 'a', 2 => 'b', 3 => 'a'];
        if ($owner = $documents[$id] ?? false) {
            if ($owner != $this->accessControl->id && 'admin' != $this->accessControl->role)
                throw new HttpException(403, 'permission denied.');
            return 'protected document, only user who owns it and admin can access';

        }
        throw new HttpException(404, 'document does not exist.');
    }

    /**
     * @access protected
     * @class  AccessControl {@requires admin}
     */
    public function admin()
    {
        return "protected api, only admin can access";
    }

}
