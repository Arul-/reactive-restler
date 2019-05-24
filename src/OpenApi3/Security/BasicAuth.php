<?php


namespace Luracast\Restler\OpenApi3\Security;


class BasicAuth extends Scheme
{
    protected $type = 'http';
    protected $scheme = 'basic';
}