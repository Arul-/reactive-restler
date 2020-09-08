<?php namespace Luracast\Restler\Data;

use Luracast\Restler\Contracts\ValueObjectInterface;

/**
 * ValueObject base class, you may use this class to create your
 * iValueObjects quickly
 */
class ValueObject implements ValueObjectInterface
{

    /**
     * @param array $properties
     * @return static
     */
    public static function __set_state(array $properties)
    {
        $class = get_called_class();
        /** @var ValueObject $instance */
        $instance = new $class ();
        $instance->applyProperties($properties);
        return $instance;
    }

    protected function applyProperties(array $properties, bool $filter = false)
    {
        if ($filter) {
            $vars = get_object_vars($this);
            foreach ($properties as $property => $value) {
                if (property_exists($this, $property)) {
                    // see if the property is accessible
                    if (array_key_exists($property, $vars)) {
                        $this->{$property} = $value;
                    } else {
                        $method = 'set' . ucfirst($property);
                        if (method_exists($this, $method)) {
                            call_user_func([
                                $this,
                                $method
                            ], $value);
                        }
                    }
                }
            }
        } else {
            foreach ($properties as $property => $value) {
                $this->{$property} = $value;
            }
        }
    }

    public function __toString()
    {
        return ' new ' . get_called_class() . '() ';
    }

    public function __debugInfo()
    {
        return $this->jsonSerialize();
    }

    public function jsonSerialize(): array
    {
        $r = get_object_vars($this);
        $methods = get_class_methods($this);
        foreach ($methods as $m) {
            if (substr($m, 0, 3) == 'get') {
                $r [lcfirst(substr($m, 3))] = @$this->{$m} ();
            }
        }
        return $r;
    }

}

