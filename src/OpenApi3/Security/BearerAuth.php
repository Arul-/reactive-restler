<?php


namespace Luracast\Restler\OpenApi3\Security;


class BearerAuth extends Scheme
{
    protected $type = 'http';
    protected $scheme = 'bearer';
}