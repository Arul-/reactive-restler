<?php

namespace Luracast\Restler;

use ArrayObject as Base;
use BadMethodCallException;

/*
 * @method array chunk(array $array, int $size, bool $preserve_keys = false) Split an array into chunks
 * @method array column(array $input, mixed $column_key, mixed $index_key = null) Return the values from a single
 * column in the input array
 * @method array combine(array $keys, array $values) Creates an array by using one array for keys and another for
 * its values
 */
class ArrayObject extends Base
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
                //TODO: add more array methods and document them
                $func = "array_$name";
                return call_user_func_array($func, array_merge(array($this->getArrayCopy()), $argv));
        }
        throw new BadMethodCallException(__CLASS__ . '->' . $name);
    }

    public static function fromArray(array $input)
    {
        $instance = new static($input);
        foreach ($input as $k => $v) {
            if (is_array($v)) {
                $instance[$k] = static::fromArray($v); //RECURSION
            }
        }
        return $instance;
    }

    public function nested(...$keys)
    {
        if (count($keys) == 1) {
            $keys = explode('.', $keys[0]);
        }
        $from = $this;
        foreach ($keys as $key) {
            if (isset($from[$key])) {
                $from = $from[$key];
                continue;
            }
            return null;
        }
        return $from;
    }
}