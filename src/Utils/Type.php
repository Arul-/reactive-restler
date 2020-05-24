<?php

namespace Luracast\Restler\Utils;


class Type
{
    const SCALAR = '|bool|boolean|int|integer|float|string|';
    const PRIMITIVE = '|array' . self::SCALAR;
    const SIMPLE = '|resource' . self::PRIMITIVE;

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
        return (boolean)strpos(static::PRIMITIVE, strtolower($type));
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

    /**
     * @param string $class
     * @param string $superClass
     * @return bool
     */
    public static function isSameOrSubclass(string $class, string $superClass)
    {
        return $class == $superClass || isset(class_implements($class)[$superClass]);
    }
}
