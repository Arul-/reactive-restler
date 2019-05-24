<?php


namespace Luracast\Restler\OpenApi3\Security;


use JsonSerializable;

abstract class Scheme
{
    protected $type;

    public function toArray()
    {
        return get_object_vars($this);
    }
}