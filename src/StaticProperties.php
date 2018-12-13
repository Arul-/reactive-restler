<?php

namespace Luracast\Restler;

use ArrayObject;
use BadMethodCallException;

class StaticProperties extends ArrayObject
{
    public static function fromArray(array $input): self
    {
        $instance = new static($input);
        foreach ($input as $k => $v) {
            if (is_array($v)) {
                $instance[$k] = static::fromArray($v); //RECURSION
            }
        }
        return $instance;
    }

    public static function forClass(string $className): self
    {
        return static::fromArray(get_class_vars($className));
    }
}