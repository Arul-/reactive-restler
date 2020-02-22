<?php namespace Luracast\Restler\Data;

use Luracast\Restler\Router;
use Luracast\Restler\Utils\CommentParser;
use Luracast\Restler\Utils\Text;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use Reflector;

/**
 * ValueObject for validation information. An instance is created and
 * populated by Restler to pass it to iValidate implementing classes for
 * validation
 */
class Param extends Type
{
    const KEEP_NON_NUMERIC = false;
    const KEEP_NUMERIC = true;

    /**
     * Name of the variable being validated
     *
     * @var string variable name
     */
    public string $name;
    /**
     * @var int
     */
    public int $index;
    /**
     * @var string proper name for given parameter
     */
    public string $label;
    /**
     * @var string|null html element that can be used to represent the parameter for
     *      input
     */
    public ?string $field;
    /**
     * @var mixed default value for the parameter
     */
    public $default;

    /**
     * @var bool is it required or not
     */
    public bool $required;

    /**
     * @var string body or header or query where this parameter is coming from
     *      in the http request
     */
    public string $from;

    /**
     * Should we attempt to fix the value?
     * When set to false validation class should throw
     * an exception or return false for the validate call.
     * When set to true it will attempt to fix the value if possible
     * or throw an exception or return false when it cant be fixed.
     *
     * @var boolean true or false
     */
    public bool $fix = false;

    // ==================================================================
    //
    // VALUE RANGE
    //
    // ------------------------------------------------------------------
    /**
     * Given value should match one of the values in the array
     *
     * @var string[] of choices to match to
     */
    public array $choice;
    /**
     * If the type is string it will set the lower limit for length
     * else will specify the lower limit for the value
     *
     * @var number minimum value
     */
    public int $min;
    /**
     * If the type is string it will set the upper limit limit for length
     * else will specify the upper limit for the value
     *
     * @var number maximum value
     */
    public int  $max;

    // ==================================================================
    //
    // REGEX VALIDATION
    //
    // ------------------------------------------------------------------
    /**
     * RegEx pattern to match the value
     *
     * @var string regular expression
     */
    public string $pattern;

    // ==================================================================
    //
    // CUSTOM VALIDATION
    //
    // ------------------------------------------------------------------
    /**
     * Rules specified for the parameter in the php doc comment.
     * It is passed to the validation method as the second parameter
     *
     * @var array custom rule set
     */
    public array $rules;

    /**
     * Specifying a custom error message will override the standard error
     * message return by the validator class
     *
     * @var string custom error response
     */
    public string $message;

    // ==================================================================
    //
    // METHODS
    //
    // ------------------------------------------------------------------

    /**
     * Name of the method to be used for validation.
     * It will be receiving two parameters $input, $rules (array)
     *
     * @var string validation method name
     */
    public string $method;

    /**
     * Instance of the API class currently being called. It will be null most of
     * the time. Only when method is defined it will contain an instance.
     * This behavior is for lazy loading of the API class
     *
     * @var null|object will be null or api class instance
     */
    public ?object $apiClassInstance = null;

    public static function from(Reflector $reflector, array $metadata = [])
    {
        $instance = parent::from($reflector, $metadata);
        return $instance;
    }

    public static function fromFunction(ReflectionFunctionAbstract $function, ?array $doc = null, array $scope = []): array
    {
        if (is_null($doc)) {
            $doc = CommentParser::parse($function->getDocComment());
        }
        if (empty($scope)) {
            if ($function instanceof ReflectionMethod) {
                /** @var ReflectionMethod $method */
                $method = $function;
                $scope = Router::scope($method->getDeclaringClass());
            } elseif ($function instanceof ReflectionFunction) {
                /** @var ReflectionFunction $fn */
                $fn = $function;
                $scope = Router::scope($fn->getClosureScopeClass());
            }
        }
        $params = [];
        $position = 0;
        foreach ($function->getParameters() as $reflectionParameter) {
            $metadata = $doc['param'][$position] ?? [];
            /** @var static $param */
            $param = static::from($reflectionParameter, $metadata);
            $param->name = $reflectionParameter->getName();
            $param->index = $position;
            $param->label = $metadata[CommentParser::$embeddedDataName]['label']
                ?? Text::title($param->name);
            $param->default = $reflectionParameter->isDefaultValueAvailable()
                ? $reflectionParameter->getDefaultValue()
                : null;
            $param->format = $metadata[CommentParser::$embeddedDataName]['format']
                ?? Router::$formatTypesByName[$param->name]
                ?? null;
            $params[] = $param;
            $position++;
        }
        return $params;
    }

    public static function filterArray(array $data, bool $onlyNumericKeys): array
    {
        $r = [];
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                if ($onlyNumericKeys) {
                    $r[$key] = $value;
                }
            } elseif (!$onlyNumericKeys) {
                $r[$key] = $value;
            }
        }
        return $r;
    }

    public function content($index = 0): self
    {
        return Param::parse([
            'name' => $this->name . '[' . $index . ']',
            'type' => $this->contentType,
            'children' => $this->properties,
            'required' => true,
        ]);
    }

    public static function parse(array $metadata): self
    {
        $instance = new self();
        $properties = get_object_vars($instance);
        unset($properties['contentType']);
        foreach ($properties as $property => $value) {
            $instance->{$property} = $instance->getProperty($metadata, $property);
        }
        $inner = $metadata['properties'] ?? null;
        $instance->rules = !empty($inner) ? $inner + $metadata : $metadata;
        unset($instance->rules['properties']);
        if (is_string($instance->type) && $instance->type == 'integer') {
            $instance->type = 'int';
        }
        $instance->scalar = \Luracast\Restler\Utils\Type::isPrimitive($instance->type);
        return $instance;
    }

    private function getProperty(array &$from, $property)
    {
        $p = $from[$property] ?? null;
        unset($from[$property]);
        $p2 = $from[CommentParser::$embeddedDataName][$property] ?? null;
        unset($from[CommentParser::$embeddedDataName][$property]);

        if ($property == 'type' && $p2) {
            $this->contentType = $p2;
            return $p;
        }
        $r = $p2 ?? $p ?? null;
        if (!is_null($r)) {
            if ($property == 'min' || $property == 'max') {
                return static::numericValue($r);
            } elseif ($property == 'required' || $property == 'fix') {
                return static::booleanValue($r);
            } elseif ($property == 'choice') {
                return static::arrayValue($r);
            } elseif ($property == 'pattern') {
                return static::stringValue($r);
            }
        }
        return $r;
    }

    public static function numericValue($value)
    {
        return ( int )$value == $value
            ? ( int )$value
            : floatval($value);
    }

    public static function booleanValue($value)
    {
        return is_bool($value)
            ? $value
            : $value !== 'false';
    }

    public static function arrayValue($value)
    {
        return is_array($value) ? $value : [$value];
    }

    public static function stringValue($value, $glue = ',')
    {
        return is_array($value)
            ? implode($glue, $value)
            : ( string )$value;
    }
}

