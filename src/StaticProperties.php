<?php

namespace Luracast\Restler;

class StaticProperties
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
}