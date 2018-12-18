<?php

namespace Luracast\Restler;

use ArrayObject;
use BadMethodCallException;

/**
 * Class StaticProperties
 * @package Luracast\Restler
 *
 * @method array chunk(array $array, int $size, bool $preserve_keys = false) Split an array into chunks
 * @method array column(array $input, mixed $column_key, mixed $index_key = null) Return the values from a single
 * column in the input array
 * @method array combine(array $keys, array $values) Creates an array by using one array for keys and another for
 * its values
 *
 */
class StaticProperties extends ArrayObject
{
    public function __construct($input = array())
    {
        parent::__construct($input, self::ARRAY_AS_PROPS);
    }


    public function __call($name, $argv)
    {
        switch ($name) {
            case 'chunk':
            case 'column':
            case 'combine':
                $func = "array_$name";
                return call_user_func_array($func, array_merge(array($this->getArrayCopy()), $argv));
        }
        throw new BadMethodCallException(__CLASS__ . '->' . $name);
    }

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