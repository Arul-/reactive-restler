<?php


namespace Luracast\Restler\Data;


use Luracast\Restler\Contracts\ValueObjectInterface;
use Luracast\Restler\Utils\CommentParser;
use Luracast\Restler\Utils\Type as TypeUtil;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use Reflector;

class Type implements ValueObjectInterface
{
    private const DIRECT_PROPERTIES = ['type', 'multiple', 'nullable', 'scalar'];
    /**
     * Data type of the variable being validated.
     * It will be mostly string
     *
     * @var string only single type is allowed. if multiple types are specified,
     * Restler will pick the first. if null is one of the values, it will be simply set the nullable flag
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

    public function apply(?ReflectionType $reflectionType, array $types = ['string'], array $subTypes = ['string'])
    {
        $name = $types[0];
        $n = false;
        if ($reflectionType && ($n = $reflectionType->getName()) && $n !== 'Generator') {
            $name = $n;
        }
        $this->nullable = in_array('null', $types);
        if ('array' == $name) {
            $this->multiple = true;
            $this->type = $subTypes[0];
            $this->scalar = TypeUtil::isScalar($subTypes[0]);
        } else {
            $this->multiple = false;
            $this->type = $name;
            if ($reflectionType) {
                $this->nullable = $reflectionType->allowsNull();
                $this->scalar = $reflectionType->isBuiltin();
            } else {
                $this->scalar = TypeUtil::isScalar($types[0]);
            }
        }
    }

    /**
     * @param ReflectionProperty|ReflectionParameter $reflector
     * @param array $metadata
     * @return static
     */
    public static function from(Reflector $reflector, array $metadata = [])
    {
        $instance = new static();
        $types = $metadata['type'] ?? ['array'];
        $itemTypes = $metadata[CommentParser::$embeddedDataName]['type'] ?? ['string'];
        $instance->description = $metadata['description'] ?? '';
        $instance->apply(
            $reflector->hasType() ? $reflector->getType() : null,
            $types,
            $itemTypes
        );
        return $instance;
    }

    public static function fromProperty(ReflectionProperty $prop)
    {
        $instance = new static();
        $var = ['type' => ['string']];
        try {
            $var = CommentParser::parse($prop->getDocComment() ?? '')['var'] ?? $var;
        } catch (\Exception $e) {
            //ignore
        }
        return static::from($prop, $var);

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
