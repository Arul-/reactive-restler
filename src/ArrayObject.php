<?php

namespace Luracast\Restler;

use ArrayObject as Base;
use BadMethodCallException;

/*
 * @method array chunk(int $size, bool $preserve_keys = false) Split an array into chunks
 * @method array column(mixed $column_key, mixed $index_key = null) Return the values from a single
 * column in the input array
 * @method array combine(array $keys, array $values) Creates an array by using one array for keys and another for
 * its values
 * @method array slice(int $offset, int $length = null, bool $preserve_keys = false) Extract a slice as an array
 * @method self splice(int $offset, int $length = count($input), mixed $replacement = array()) Remove a portion of the arrayObject and replace it with something else
 * @method mixed shift() Shift an element off the beginning of arrayObject
 * @method mixed pop() Pop the element off the end of arrayObject
 */

class ArrayObject extends Base
{
    public function __construct($input = array())
    {
        parent::__construct($input, self::ARRAY_AS_PROPS);
    }


    public function __call($name, $argv)
    {
        $found = false;
        $modifier = false;
        $func = null;
        switch ($name) {
            //MOD functions
            case 'shift':
            case 'pop':
            case 'splice':
                $found = true;
                $modifier = true;
                break;
            case 'slice':
            case 'chunk':
            case 'column':
            case 'combine':
                $found = true;
                break;
        }
        //TODO: add more array methods and document them
        if ($found) {
            if (!$func) {
                $func = "array_$name";
            }
            $result = call_user_func_array($func, array_merge(array($this->getArrayCopy()), $argv));
            if (!$modifier) {
                return $result;
            }
            $this->exchangeArray($result);
            return $this;
        }
        throw new BadMethodCallException(__CLASS__ . '->' . $name);
    }

    public function merge(ArrayObject $arrayObject, bool $overwrite = false)
    {
        foreach ($arrayObject as $key => $val) {
            if ($overwrite || !$this->offsetExists($key)) {
                $this[$key] = $val;
            }
        }
        return $this;
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

    public function jsonSerialize()
    {
        return $this->getArrayCopy();
    }
}