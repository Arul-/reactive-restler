<?php


namespace Luracast\Restler\Data;


use Exception;
use Luracast\Restler\Contracts\ValueObjectInterface;
use Luracast\Restler\Router;
use Luracast\Restler\Utils\ClassName;
use Luracast\Restler\Utils\CommentParser;
use Luracast\Restler\Utils\Type as TypeUtil;
use ReflectionClass;
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
    public $type = 'string';

    /**
     * @var bool is it a list?
     */
    public $multiple = false;

    /**
     * @var bool can it hold null value?
     */
    public $nullable = true;

    /**
     * @var bool does it hold scalar data or object data
     */
    public $scalar = true;

    /**
     * @var string|null if the given data can be classified to sub types it will be specified here
     */
    public $format = null;

    /**
     * @var array|null of children to be validated. used only for non scalar type
     */
    public $properties = null;

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var string|null
     */
    public $reference = null;

    /**
     * @var array|null
     */
    public $children = null;


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

    protected function apply(?ReflectionType $reflectionType, array $types, array $subTypes, array $scope = [])
    {
        $name = $types[0];
        if ($reflectionType && ($n = $reflectionType->getName()) && $n !== 'Generator') {
            $name = $n;
        }
        $this->nullable = in_array('null', $types);
        if ('array' == $name && count($subTypes)) {
            $this->multiple = true;
            $this->type = $subTypes[0];
            $this->scalar = TypeUtil::isScalar($subTypes[0]);
        } else {
            $this->multiple = false;
            $this->type = $name;
            if ($reflectionType) {
                $this->nullable = $reflectionType->allowsNull();
                $this->scalar = 'array' !== $name && $reflectionType->isBuiltin();
            } else {
                $this->scalar = 'array' !== $name && TypeUtil::isScalar($types[0]);
            }
        }
        if (!$this->scalar && $qualified = ClassName::resolve($this->type, $scope)) {
            $this->type = $qualified;
            $class = new ReflectionClass($qualified);
            $this->properties = static::fromClass($class);
        }

    }

    /**
     * @param Reflector|null $reflector
     * @param array $metadata
     * @param array $scope
     * @return static
     */
    protected static function from(?Reflector $reflector, array $metadata = [], array $scope = [])
    {
        $instance = new static();
        $types = $metadata['type'] ?? [];
        $itemTypes = $metadata[CommentParser::$embeddedDataName]['type'] ?? [];
        $instance->description = $metadata['description'] ?? '';
        $instance->apply(
            method_exists($reflector, 'hasType') && $reflector->hasType()
                ? $reflector->getType() : null,
            $types,
            $itemTypes,
            $scope
        );
        return $instance;
    }

    public static function fromProperty(?ReflectionProperty $property, ?array $doc = null, array $scope = [])
    {
        if ($doc) {
            $var = $doc;
        } else {
            try {
                $var = CommentParser::parse($property->getDocComment() ?? '')['var']
                    ?? ['type' => ['string']];
            } catch (Exception $e) {
                //ignore
            }
        }
        return static::from($property, $var, $scope);
    }

    public static function fromClass(ReflectionClass $reflectionClass, array $selectedProperties = [], array $requiredProperties = [])
    {
        $isParameter = Param::class == get_called_class();
        $filter = !empty($selectedProperties);
        $properties = [];
        $scope = Router::scope($reflectionClass);
        //When Magic properties exist
        if ($c = CommentParser::parse($reflectionClass->getDocComment())) {
            $p = 'property';
            $magicProperties = empty($c[$p]) ? [] : $c[$p];
            $p .= '-' . ($isParameter ? 'write' : 'read');
            if (!empty($c[$p])) {
                $magicProperties = array_merge($magicProperties, $c[$p]);
            }
            foreach ($magicProperties as $magicProperty) {
                if (!$name = $magicProperty['name'] ?? false) {
                    throw new Exception('@property comment is not properly defined in ' . $reflectionClass->getName() . ' class');
                }
                if ($filter && !in_array($name, $selectedProperties)) {
                    continue;
                }
                $properties[$name] = static::from(null, $magicProperty, $scope);
            }
        } else {
            $reflectionProperties = $reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC);
            foreach ($reflectionProperties as $reflectionProperty) {
                $name = $reflectionProperty->getName();
                if ($filter && !in_array($name, $selectedProperties)) {
                    continue;
                }
                $properties[$name] = static::fromProperty($reflectionProperty, null, $scope);
            }
        }
        $modifyRequired = !empty($requiredProperties);
        if ($modifyRequired) {
            /**
             * @var string $name
             * @var Type $property
             */
            foreach ($properties as $name => $property) {
                $property->required = in_array($name, $requiredProperties);
            }
        }
        return $properties;
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
