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

    public function toArray(string $basePath = '/')
    {
        $result = array_filter(get_object_vars($this));
        foreach ($result as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if (is_object($v)) {
                        $result[$key][$k] = $v->toArray($basePath);
                    }
                }
            } elseif (is_object($value)) {
                $result[$key] = $value->toArray($basePath);
            }
        }
        return $result;
    }
}
