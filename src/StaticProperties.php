<?php

namespace Luracast\Restler;

use ArrayAccess;

class StaticProperties implements ArrayAccess
{
    private $properties = [];
    /**
     * @var string
     */
    private $className;

    public function __construct(string $className)
    {

        $this->className = $className;
    }

    public function &__get($name)
    {
        if (property_exists($this->className, $name)) {
            $value = $this->className::$$name;
            if (!array_key_exists($name, $this->properties)) {
                $this->properties[$name] = [$value, $value];
            }
            $newValue = &$this->properties[$name][0];
            $oldValue = &$this->properties[$name][1];
            if ($value === $oldValue) {
                return $newValue;
            }
            return $value;
        }
        return null;
    }

    public function __isset($name)
    {
        return property_exists($this->className, $name);
    }

    public function __set($name, $value)
    {
        if (property_exists($this->className, $name)) {
            $this->properties[$name] = [$value, $this->className::$$name];
        }
    }

    public function __unset($name)
    {
        unset($this->properties[$name]);
    }

    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    public function &offsetGet($offset)
    {
        return $this->__get($offset);
    }

    public function offsetSet($offset, $value)
    {
        return $this->__set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }
}