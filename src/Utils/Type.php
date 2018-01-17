<?php namespace Luracast\Restler\Utils;


class Type
{

    /**
     * verify if the given data type string is scalar or not
     *
     * @static
     *
     * @param string $type data type as string
     *
     * @return bool true or false
     */
    public static function isObjectOrArray(string $type)
    {
        if (is_array($type)) {
            foreach ($type as $t) {
                if (static::isObjectOrArray($t)) {
                    return true;
                }
            }
            return false;
        }
        return !(boolean)strpos('|bool|boolean|int|float|string|', $type);
    }
}