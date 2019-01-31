<?php namespace Luracast\Restler\Utils;


use JsonSerializable;

class Convert
{
    /**
     * @var array key value pairs for fixing value types using functions.
     * For example
     *
     *      'id'=>'intval'      will make sure all values of the id properties
     *                          will be converted to integers intval function
     *      'password'=> null   will remove all the password entries
     */
    public static $fix = array();
    /**
     * @var bool|callable
     */
    public static $stringEncoderFunction = false;
    /**
     * @var bool|callable
     */
    public static $numberEncoderFunction = false;

    /**
     * @var string character that is used to identify sub objects
     *
     * For example
     *
     * when Object::$separatorChar = '.';
     *
     * array('my.object'=>true) will result in
     *
     * array(
     *    'my'=>array('object'=>true)
     * );
     */
    public static $separatorChar = null;
    /**
     * @var bool set it to true when empty arrays, blank strings, null values
     * to be automatically removed from response
     */
    public static $removeEmpty = false;
    /**
     * @var bool set it to true to remove all null values from the result
     */
    public static $removeNull = false;


    public static function toArray($object, bool $forceObjectTypeWhenEmpty = false)
    {
        $nested = false;
        if (is_object($object)) {
            $nested = true;
            if ($object instanceof JsonSerializable) {
                $object = $object->jsonSerialize();
            } elseif (method_exists($object, '__sleep')) {
                $properties = $object->__sleep();
                $array = array();
                foreach ($properties as $key) {
                    $value = static::toArray($object->{$key},
                        $forceObjectTypeWhenEmpty);
                    if (static::$stringEncoderFunction && is_string($value)) {
                        $value = static::$stringEncoderFunction($value);
                    } elseif (static::$numberEncoderFunction && is_numeric($value)) {
                        $value = static::$numberEncoderFunction($value);
                    }
                    $array [$key] = $value;
                }
                return $array;
            }
        } elseif (is_array($object)) {
            $nested = true;
        }
        if ($nested) {
            $count = 0;
            $array = array();
            foreach ($object as $key => $value) {
                if (
                    is_string(static::$separatorChar) &&
                    false !== strpos($key, static::$separatorChar)
                ) {
                    list($key, $obj) = explode(static::$separatorChar, $key, 2);
                    $object[$key][$obj] = $value;
                    $value = $object[$key];
                }
                if (static::$removeEmpty && empty($value) && !is_numeric($value) && !is_bool($value)) {
                    continue;
                } elseif (static::$removeNull && is_null($value)) {
                    continue;
                }
                if (array_key_exists($key, static::$fix)) {
                    if (isset(static::$fix[$key])) {
                        $value = call_user_func(static::$fix[$key], $value);
                    } else {
                        continue;
                    }
                }
                $value = static::toArray($value, $forceObjectTypeWhenEmpty);
                if (static::$stringEncoderFunction && is_string($value)) {
                    $value = static::$encoderFunctionName ($value);
                } elseif (static::$numberEncoderFunction && is_numeric($value)) {
                    $value = static::$numberEncoderFunction ($value);
                }
                $array [$key] = $value;
                $count++;
            }
            return $forceObjectTypeWhenEmpty && $count == 0 ? $object : $array;
        }

        return $object;
    }

    public static function toNumber($value)
    {
        return ( int )$value == $value ? ( int )$value : floatval($value);
    }

    public static function toBool($value)
    {
        return is_bool($value) ? $value : $value !== 'false';
    }
}