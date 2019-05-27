<?php


namespace Luracast\Restler\OpenApi3\Security;


abstract class Scheme
{
    const TYPE_API_KEY = 'apiKey';
    const TYPE_HTTP = 'http';
    const TYPE_OAUTH2 = 'oauth2';
    const TYPE_OPEN_ID_CONNECT = 'openIdConnect';

    const HTTP_SCHEME_BASIC = 'basic';
    const HTTP_SCHEME_BEARER = 'bearer';

    protected $type;
    protected $description;

    public function toArray()
    {
        return array_filter(get_object_vars($this));
    }
}