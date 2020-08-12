<?php namespace Luracast\Restler\Data;

use Exception;
use Luracast\Restler\Defaults;
use Luracast\Restler\Router;
use Luracast\Restler\Utils\CommentParser;
use Luracast\Restler\Utils\Text;
use Luracast\Restler\Utils\Type as TypeUtil;
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

    const FROM_PATH = 'path';
    const FROM_QUERY = 'query';
    const FROM_BODY = 'body';
    const FROM_HEADER = 'header';

    /**
     * Name of the variable being validated
     *
     * @var string variable name
     */
    public $name;
    /**
     * @var int
     */
    public $index;
    /**
     * @var string proper name for given parameter
     */
    public $label;
    /**
     * @var string|null html element that can be used to represent the parameter for
     *      input
     */
    public $field;
    /**
     * @var mixed default value for the parameter
     */
    public $default;

    /**
     * @var bool is it required or not
     */
    public $required;

    /**
     * @var string body or header or query where this parameter is coming from
     *      in the http request
     */
    public $from;

    /**
     * Should we attempt to fix the value?
     * When set to false validation class should throw
     * an exception or return false for the validate call.
     * When set to true it will attempt to fix the value if possible
     * or throw an exception or return false when it cant be fixed.
     *
     * @var boolean true or false
     */
    public $fix = false;

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
    public $choice;
    /**
     * If the type is string it will set the lower limit for length
     * else will specify the lower limit for the value
     *
     * @var number minimum value
     */
    public $min;
    /**
     * If the type is string it will set the upper limit limit for length
     * else will specify the upper limit for the value
     *
     * @var number maximum value
     */
    public $max;

    /**
     * only for arrays
     *
     * @var int minimum array count
     */
    public $minCount;
    /**
     * Only for arrays
     *
     * @var int maximum array count
     */
    public $maxCount;

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
    public $pattern;

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
    public $rules;

    /**
     * Specifying a custom error message will override the standard error
     * message return by the validator class
     *
     * @var string custom error response
     */
    public $message;

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
    public $method;

    /**
     * Instance of the API class currently being called. It will be null most of
     * the time. Only when method is defined it will contain an instance.
     * This behavior is for lazy loading of the API class
     *
     * @var null|object will be null or api class instance
     */
    public $apiClassInstance = null;

    public static function fromMethod(ReflectionMethod $method, ?array $doc = null, array $scope = []): array
    {
        if (empty($scope)) {
            $scope = Router::scope($method->getDeclaringClass());
        }
        return static::fromAbstract($method, $doc, $scope);
    }

    public static function fromFunction(ReflectionFunction $function, ?array $doc = null, array $scope = []): array
    {
        if (empty($scope)) {
            $scope = Router::scope($function->getClosureScopeClass());
        }
        return static::fromAbstract($function, $doc, $scope);
    }

    public static function fromParameter(ReflectionParameter $parameter, ?array $doc, array $scope): self
    {
        return static::from($parameter, $doc['param'][$parameter->getPosition()] ?? [], $scope);
    }

    private static function fromAbstract(
        ReflectionFunctionAbstract $function,
        ?array $doc = null,
        array $scope = []
    ): array
    {
        if (is_null($doc)) {
            try {
                $doc = CommentParser::parse($function->getDocComment());
            } catch (Exception $e) {
                //ignore
            }
        }
        $params = [];
        foreach ($function->getParameters() as $reflectionParameter) {

            $params[] = static::fromParameter($reflectionParameter, $doc, $scope);
        }
        return array_column($params, null, 'name');
    }

    protected static function from(?Reflector $reflector, array $metadata = [], array $scope = [])
    {
        $instance = new static();
        $types = $metadata['type'] ?? [];
        $properties = $metadata[CommentParser::$embeddedDataName] ?? [];
        $itemTypes = $properties['type'] ?? [];
        unset($properties['type']);
        $instance->rules = $properties;
        $instance->description = $metadata['description'] ?? '';
        if ($reflector && method_exists($reflector, 'isDefaultValueAvailable') && $reflector->isDefaultValueAvailable()) {
            $default = $reflector->getDefaultValue();
            $instance->default = $default;
            $types[] = TypeUtil::fromValue($default);
        }
        if ($reflector && Defaults::$fullRequestDataName === $reflector->name) {
            $types = ['array'];
            $instance->format = 'associative';
            $itemTypes = [];
        } elseif (empty($types)) {
            array_unshift($types, 'string');
        } elseif (in_array('array', $types) && empty($itemTypes)) {
            array_unshift($itemTypes, 'string');
        }
        $instance->apply(
            method_exists($reflector, 'hasType') && $reflector->hasType()
                ? $reflector->getType() : null,
            $types,
            $itemTypes,
            $scope
        );
        $instance->required = TypeUtil::booleanValue($properties['required'] ?? $reflector && method_exists($reflector, 'isOptional') && !$reflector->isOptional());
        if ($reflector) {
            $instance->name = $reflector->getName();
            if (method_exists($reflector, 'getPosition')) {
                $instance->index = $reflector->getPosition();
            }
        } else {
            $instance->name = $metadata['name'] ?? null;
        }

        $instance->label = $properties['label']
            ?? Text::title($instance->name);

        if (isset($properties['min'])) {
            $instance->minCount = $properties['min'][0];
            $instance->min = $properties['min'][1];
        }
        if (isset($properties['max'])) {
            $instance->maxCount = $properties['max'][0];
            $instance->max = $properties['max'][1];
        }
        $instance->pattern = $properties['pattern'] ?? null;
        $instance->message = $properties['message'] ?? null;
        $instance->fix = $properties['fix'] ?? false;

        $instance->from = $properties['from']
            ?? (
            in_array($instance->name, Router::$prefixingParameterNames)
                ? self::FROM_PATH
                : self::FROM_BODY
            );
        if (!$instance->format) {
            $instance->format = $properties['format']
                ?? Router::$formatsByName[$instance->name]
                ?? null;
        }
        return $instance;
    }

    public static function filterArray(array $data, bool $onlyNumericKeys): array
    {
        $callback = $onlyNumericKeys ? 'is_numeric' : 'is_string';
        return array_filter($data, $callback, ARRAY_FILTER_USE_KEY);
    }
}

