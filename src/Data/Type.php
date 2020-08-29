<?php


namespace Luracast\Restler\Data;


use Exception;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GraphQLType;
use Luracast\Restler\Contracts\GenericRequestInterface;
use Luracast\Restler\Contracts\GenericResponseInterface;
use Luracast\Restler\Contracts\ValueObjectInterface;
use Luracast\Restler\Exceptions\Invalid;
use Luracast\Restler\GraphQL\GraphQL;
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
     * @inheritDoc
     */
    public static function __set_state(array $properties)
    {
        $instance = new static();
        $instance->applyProperties($properties);
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
        foreach ($filtered as $k => $v) {
            if (!is_null($v)) {
                $this->{$k} = $v;
            }
        }
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

    protected function apply(?ReflectionType $reflectionType, array $types, array $subTypes, array $scope = [])
    {
        $name = $types[0];
        if ($reflectionType && ($n = $reflectionType->getName()) && $n !== 'Generator') {
            $name = $n;
        }
        $this->nullable = in_array('null', $types);
        if (empty($types) || in_array('mixed', $types) || ($this->nullable && 1 == count($types))) {
            $this->type = 'mixed';
        } elseif ('array' == $name && count($subTypes)) {
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
                $this->scalar = TypeUtil::isScalar($types[0]);
            }
        }
        if (!$this->scalar && $qualified = ClassName::resolve($this->type, $scope)) {
            $this->type = $qualified;
            $class = new ReflectionClass($qualified);
            $isParameter = Param::class === get_called_class();
            $interface = $isParameter ? GenericRequestInterface::class : GenericResponseInterface::class;
            $method = $isParameter ? 'requests' : 'responds';

            if ($class->implementsInterface($interface)) {
                $generics = explode(',', $this->format);
                foreach ($generics as $key => $generic) {
                    if ($generic = ClassName::resolve($generic, $scope)) {
                        $generics[$key] = $generic;
                    }
                }
                /** @var Type $type */
                $type = call_user_func_array([$class->name, $method], $generics);
                $this->properties = $type->properties;
                $this->type = $type->type;
            } else {
                $this->properties = static::propertiesFromClass($class);
            }
        }
    }

    protected static function propertiesFromClass(
        ReflectionClass $reflectionClass,
        array $selectedProperties = [],
        array $requiredProperties = []
    ) {
        $isParameter = Param::class === get_called_class();
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
        }
        if (empty($magicProperties)) {
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

    public static function fromClass(ReflectionClass $reflectionClass)
    {
        $isParameter = Param::class === get_called_class();
        $interface = $isParameter ? GenericRequestInterface::class : GenericResponseInterface::class;
        $method = $isParameter ? 'requests' : 'responds';
        if ($reflectionClass->implementsInterface($interface)) {
            return call_user_func([$reflectionClass->name, $method]);
        }
        $instance = new static;
        $instance->scalar = false;
        $instance->type = $reflectionClass->name;
        $instance->properties = self::propertiesFromClass($reflectionClass);
        return $instance;
    }

    public static function fromSampleData(array $data)
    {
        if (empty($data)) {
            throw new Invalid('data can\'t be empty');
        }
        $properties = Param::filterArray($data, Param::KEEP_NON_NUMERIC);
        if (empty($properties)) {
            //array of items
            /** @var Type $value */
            $value = static::fromSampleData($data[0]);
            $value->multiple = true;
            return $value;
        }
        /** @var Type $obj */
        $obj = static::fromValue($data);
        foreach ($properties as $name => $value) {
            $obj->properties[$name] = static::fromValue($value);
        }
        return $obj;
    }

    public static function fromValue($value): Type
    {
        $instance = new static();
        if (is_scalar($value)) {
            $instance->scalar = true;
            if (is_numeric($value)) {
                $instance->type = is_float($value) ? 'float' : 'int';
            } else {
                $instance->type = 'string';
            }
        } else {
            $instance->scalar = false;
            $instance->type = 'object';

        }
        return $instance;
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        $str = '';
        if ($this->nullable) {
            $str .= '?';
        }
        $str .= $this->type;
        if ($this->multiple) {
            $str .= '[]';
        }
        $str .= '; // ' . get_called_class();
        if (!$this->scalar) {
            $str = 'new ' . $str;
        }
        return $str;
    }

    public function __debugInfo()
    {
        return $this->jsonSerialize();
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return array_filter(get_object_vars($this));
    }

    public function __sleep()
    {
        return $this->jsonSerialize();
    }

    public function toGraphQL(): GraphQLType
    {
        $type = null;
        if ($this->scalar) {
            $type = call_user_func([GraphQLType::class, $this->type]);
        } else {
            $class = ClassName::short($this->type);
            if ($this instanceof Param) {
                $class .= 'Input';
            }
            if (isset(GraphQL::$definitions[$class])) {
                $type = GraphQL::$definitions[$class];
            } else {
                $config = ['name' => $class, 'fields' => []];
                if (is_array($this->properties)) {
                    /** @var Type $property */
                    foreach ($this->properties as $name => $property) {
                        $config['fields'][$name] = $property->toGraphQL();
                    }
                }
                $type = $this instanceof Param
                    ? new InputObjectType($config)
                    : new ObjectType($config);
            }
            GraphQL::$definitions[$class] = $type;
        }
        if (!$this->nullable) {
            $type = GraphQLType::nonNull($type);
        }
        if ($this->multiple) {
            $type = GraphQLType::listOf($type);
        }
        return $type;
    }
}
