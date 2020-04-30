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

    public function all(): string
    {
        return "public api, all are welcome";
    }

    /**
     * @access protected
     * @class  AccessControl {@requires user}
     */
    public function user(): string
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
    public function documents(int $id): string
    {
        //$id => $owner
        $documents = [1 => 'a', 2 => 'b', 3 => 'a'];
        if (!$owner = $documents[$id] ?? false)
            throw new HttpException(404, 'document does not exist.');
        $this->accessControl->_verifyPermissionForDocumentOwnedBy($owner);
        return 'protected document, only user who owns it and admin can access';
    }

    /**
     * @access protected
     * @class  AccessControl {@requires admin}
     */
    public function admin(): string
    {
        return "protected api, only admin can access";
    }

}
