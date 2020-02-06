<?php


namespace Luracast\Restler\Data;


use Luracast\Restler\Contracts\ValueObjectInterface;
use ReflectionProperty;

class Type implements ValueObjectInterface
{
    /**
     * Data type of the variable being validated.
     * It will be mostly string
     *
     * @var string only single type is allowed. if multiple types are specified,
     * Restler will pick the first. if null is one of the values, it will be simple set the nullable flag
     * if multiple is true, type denotes the content type here
     */
    public string $type = 'string';

    /**
     * @var bool is it a list?
     */
    public bool $multiple = false;

    /**
     * @var bool can it hold null value?
     */
    public bool $nullable = true;

    /**
     * @var bool does it hold scalar data or object data
     */
    public bool $scalar = true;

    /**
     * @var string|null if the given data can be classified to sub types it will be specified here
     */
    public ?string $format = null;

    /**
     * @var array|null of children to be validated. used only for non scalar type
     */
    public ?array $properties = null;

    /**
     * @var string
     */
    public string $description = '';


    /**
     * @inheritDoc
     */
    public function __toString()
    {
        $str = '';
        if ($this->nullable) $str .= '?';
        $str .= $this->type;
        if ($this->multiple) $str .= '[]';
        $str .= '; // ' . get_called_class();
        if (!$this->scalar) $str = 'new ' . $str;
        return $str;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @inheritDoc
     */
    public static function __set_state(array $properties)
    {
        $instance = new static();
        $instance->applyProperties($properties);
        return $instance;
    }

    public static function fromProp(ReflectionProperty $prop)
    {
        $instance = new static();
        if ($prop->hasType()) {
            $t = $prop->getType();
            $instance->type = $ts = $t->getName();
            $instance->scalar = $t->isBuiltin() && 'array' !== $ts;
            $instance->nullable = $t->allowsNull();

        } else { //try doc comment

        }
        return $instance;
    }

    protected function applyProperties(array $properties, bool $filter = true)
    {
        if ($filter) {
            $vars = get_object_vars($this);
            $filtered = array_intersect_key($properties, $vars);
        } else {
            $filtered = $properties;
        }
        foreach ($filtered as $k => $v) if (!is_null($v)) $this->{$k} = $v;
    }
}
