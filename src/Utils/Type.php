<?php namespace Luracast\Restler\Utils;


class Type
{
    const SCALAR = '|bool|boolean|int|integer|float|string|';
    const PRIMITIVE = '|Array|array' . Type::SCALAR;

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
        return (boolean)strpos(static::SCALAR, $type);
    }

    /**
     * @param string $type
     * @return boolean
     */
    public static function isPrimitive(string $type): bool
    {
        return (boolean)strpos(static::PRIMITIVE, $type);
    }
}