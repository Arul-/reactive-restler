<?php

namespace Luracast\Restler\Utils;


class Type
{
    const SCALAR = '|bool|boolean|int|integer|float|string';
    const PRIMITIVE = '|array' . self::SCALAR;
    const SIMPLE = '|resource|mixed' . self::PRIMITIVE;

    /**
     * verify if the given data type string is scalar or not
     *
     * @static
     *
     * @param string $type data type as string
     *
     * @return bool true or false
     */
    public static function isObjectOrArray(string $type): bool
    {
        if (is_array($type)) {
            $result = true;
            foreach ($type as $t) {
                if (!static::isObjectOrArray($t)) {
                    $result = false;
                }
            }
            return $result;
        }
        return !(boolean)strpos(static::SCALAR, $type);
    }

    /**
     * @param string $type
     * @return boolean
     */
    public static function isScalar(string $type): bool
    {
        return (boolean)strpos(static::SCALAR, strtolower($type));
    }

    /**
     * @param string $type
     * @return boolean
     */
    public static function isPrimitive(string $type): bool
    {
        return (boolean)strpos(static::PRIMITIVE, strtolower($type));
    }

    /**
     * @param string $type
     * @return boolean
     */
    public static function isObject(string $type): bool
    {
        return !(boolean)strpos(static::SIMPLE, strtolower($type));
    }

    public static function implements(string $class, string $interface): bool
    {
        return isset(class_implements($class)[$interface]);
    }

    /**
     * @param string $class
     * @param string $superClass
     * @return bool
     */
    public static function matches(string $class, string $superClass): bool
    {
        return $class == $superClass || isset(class_implements($class)[$superClass]);
    }

    public static function booleanValue($value): bool
    {
        return is_bool($value)
            ? $value
            : $value !== 'false';
    }

    public static function numericValue($value)
    {
        return ( int )$value == $value
            ? ( int )$value
            : floatval($value);
    }

    public static function stringValue($value, $glue = ','): string
    {
        return is_array($value)
            ? implode($glue, $value)
            : ( string )$value;
    }

    public static function arrayValue($value): array
    {
        return is_array($value) ? $value : [$value];
    }

    public static function fromValue($value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }
        if (is_array($value)) {
            return 'array';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_numeric($value)) {
            return is_float($value) ? 'float' : 'int';
        }
        return is_null($value) ? 'null' : 'string';
    }
}
