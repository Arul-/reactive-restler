<?php


namespace Luracast\Restler\OpenApi3\Security;


abstract class OAuth2Flow
{
    protected $scopes = [];
    protected $refreshUrl;

    public function toArray()
    {
        return get_object_vars($this);
    }
}