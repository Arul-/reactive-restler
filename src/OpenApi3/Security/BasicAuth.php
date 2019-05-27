<?php


namespace Luracast\Restler\OpenApi3\Security;


class BasicAuth extends Scheme
{
    protected $type = Scheme::TYPE_HTTP;
    protected $scheme = self::HTTP_SCHEME_BASIC;
}