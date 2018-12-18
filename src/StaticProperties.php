<?php

namespace Luracast\Restler;

class StaticProperties extends ArrayObject
{
    public static function forClass(string $className): self
    {
        return static::fromArray(get_class_vars($className));
    }
}