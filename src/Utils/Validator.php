<?php namespace Luracast\Restler\Utils;

use Luracast\Restler\CommentParser;
use Luracast\Restler\Data\Invalid;
use Luracast\Restler\Data\ValidationInfo;
use Luracast\Restler\Data\Validator as OldValidator;
use Luracast\Restler\Format\HtmlFormat;
use Luracast\Restler\HttpException;
use Luracast\Restler\RestException;
use Luracast\Restler\Scope;
use Luracast\Restler\Util;

class Validator extends OldValidator
{
    public static function validate($input, ValidationInfo $info, $full = null)
    {
        $html = Scope::get('Restler')->responseFormat instanceof HtmlFormat;
        $name = $html ? "<strong>$info->label</strong>" : "`$info->name`";
        if (
            isset(static::$preFilters['*']) &&
            is_scalar($input) &&
            is_callable($func = static::$preFilters['*'])
        ) {
            $input = $func($input);
        }

        try {
            if (is_null($input)) {
                if ($info->required) {
                    throw new HttpException(400,
                        "$name is required.");
                }
                return null;
            }
            $error = isset ($info->message)
                ? $info->message
                : "Invalid value specified for $name";

            //if a validation method is specified
            if (!empty($info->method)) {
                $method = $info->method;
                $info->method = '';
                $r = self::validate($input, $info);
                return $info->apiClassInstance->{$method} ($r);
            }

            // when type is an array check if it passes for any type
            if (is_array($info->type)) {
                //trace("types are ".print_r($info->type, true));
                $types = $info->type;
                foreach ($types as $type) {
                    $info->type = $type;
                    try {
                        $r = self::validate($input, $info);
                        if ($r !== false) {
                            return $r;
                        }
                    } catch (RestException $e) {
                        // just continue
                    }
                }
                throw new HttpException(400, $error);
            }

            //patterns are supported only for non numeric types
            if (isset ($info->pattern)
                && $info->type != 'int'
                && $info->type != 'float'
                && $info->type != 'number'
            ) {
                if (!preg_match($info->pattern, $input)) {
                    throw new HttpException(400, $error);
                }
            }

            if (isset ($info->choice)) {
                if (!$info->required && empty($input)) {
                    //since its optional, and empty let it pass.
                    $input = null;
                } elseif (is_array($input)) {
                    foreach ($input as $i) {
                        if (!in_array($i, $info->choice)) {
                            $error .= ". Expected one of (" . implode(',', $info->choice) . ").";
                            throw new HttpException(400, $error);
                        }
                    }
                } elseif (!in_array($input, $info->choice)) {
                    $error .= ". Expected one of (" . implode(',', $info->choice) . ").";
                    throw new HttpException(400, $error);
                }
            }

            if (method_exists($class = get_called_class(), $info->type) && $info->type != 'validate') {
                if (!$info->required && empty($input)) {
                    //optional parameter with a empty value assume null
                    return null;
                }
                try {
                    return call_user_func("$class::$info->type", $input, $info);
                } catch (Invalid $e) {
                    throw new HttpException(400, $error . '. ' . $e->getMessage());
                }
            }

            switch ($info->type) {
                case 'int' :
                case 'float' :
                case 'number' :
                    if (!is_numeric($input)) {
                        $error .= '. Expecting '
                            . ($info->type == 'int' ? 'integer' : 'numeric')
                            . ' value';
                        break;
                    }
                    if ($info->type == 'int' && (int)$input != $input) {
                        if ($info->fix) {
                            $r = (int)$input;
                        } else {
                            $error .= '. Expecting integer value';
                            break;
                        }
                    } else {
                        $r = $info->numericValue($input);
                    }
                    if (isset ($info->min) && $r < $info->min) {
                        if ($info->fix) {
                            $r = $info->min;
                        } else {
                            $error .= ". Minimum required value is $info->min.";
                            break;
                        }
                    }
                    if (isset ($info->max) && $r > $info->max) {
                        if ($info->fix) {
                            $r = $info->max;
                        } else {
                            $error .= ". Maximum allowed value is $info->max.";
                            break;
                        }
                    }
                    return $r;

                case 'string' :
                case 'password' : //password fields with string
                case 'search' : //search field with string
                    if (!is_string($input)) {
                        $error .= '. Expecting alpha numeric value';
                        break;
                    }
                    if ($info->required && $input === '') {
                        $error = "$name is required.";
                        break;
                    }
                    $r = strlen($input);
                    if (isset ($info->min) && $r < $info->min) {
                        if ($info->fix) {
                            $input = str_pad($input, $info->min, $input);
                        } else {
                            $char = $info->min > 1 ? 'characters' : 'character';
                            $error .= ". Minimum $info->min $char required.";
                            break;
                        }
                    }
                    if (isset ($info->max) && $r > $info->max) {
                        if ($info->fix) {
                            $input = substr($input, 0, $info->max);
                        } else {
                            $char = $info->max > 1 ? 'characters' : 'character';
                            $error .= ". Maximum $info->max $char allowed.";
                            break;
                        }
                    }
                    return $input;

                case 'bool':
                case 'boolean':
                    if ($input === 'true' || $input === true) {
                        return true;
                    }
                    if (is_numeric($input)) {
                        return $input > 0;
                    }
                    $error .= '. Expecting boolean value';
                    break;
                case 'array':
                    if ($info->fix && is_string($input)) {
                        $input = explode(CommentParser::$arrayDelimiter, $input);
                    }
                    if (is_array($input)) {
                        $contentType =
                            Util::nestedValue($info, 'contentType') ?: null;
                        if ($info->fix) {
                            if ($contentType == 'indexed') {
                                $input = $info->filterArray($input, true);
                            } elseif ($contentType == 'associative') {
                                $input = $info->filterArray($input, true);
                            }
                        } elseif (
                            $contentType == 'indexed' &&
                            array_values($input) != $input
                        ) {
                            $error .= '. Expecting a list of items but an item is given';
                            break;
                        } elseif (
                            $contentType == 'associative' &&
                            array_values($input) == $input &&
                            count($input)
                        ) {
                            $error .= '. Expecting an item but a list is given';
                            break;
                        }
                        $r = count($input);
                        if (isset ($info->min) && $r < $info->min) {
                            $item = $info->max > 1 ? 'items' : 'item';
                            $error .= ". Minimum $info->min $item required.";
                            break;
                        }
                        if (isset ($info->max) && $r > $info->max) {
                            if ($info->fix) {
                                $input = array_slice($input, 0, $info->max);
                            } else {
                                $item = $info->max > 1 ? 'items' : 'item';
                                $error .= ". Maximum $info->max $item allowed.";
                                break;
                            }
                        }
                        if (
                            isset($contentType) &&
                            $contentType != 'associative' &&
                            $contentType != 'indexed'
                        ) {
                            $name = $info->name;
                            $info->type = $contentType;
                            unset($info->contentType);
                            foreach ($input as $key => $chinput) {
                                $info->name = "{$name}[$key]";
                                $input[$key] = static::validate($chinput, $info);
                            }
                        }
                        return $input;
                    } elseif (isset($contentType)) {
                        $error .= '. Expecting items of type ' .
                            ($html ? "<strong>$contentType</strong>" : "`$contentType`");
                        break;
                    }
                    break;
                case 'mixed':
                case 'unknown_type':
                case 'unknown':
                case null: //treat as unknown
                    return $input;
                default :
                    if (!is_array($input)) {
                        break;
                    }
                    //do type conversion
                    if (class_exists($info->type)) {
                        $input = $info->filterArray($input, false);
                        $implements = class_implements($info->type);
                        if (
                            is_array($implements) &&
                            in_array('Luracast\\Restler\\Data\\iValueObject', $implements)
                        ) {
                            return call_user_func(
                                "{$info->type}::__set_state", $input
                            );
                        }
                        $class = $info->type;
                        $instance = new $class();
                        if (is_array($info->children)) {
                            if (
                                empty($input) ||
                                !is_array($input) ||
                                $input === array_values($input)
                            ) {
                                $error .= '. Expecting an item of type ' .
                                    ($html ? "<strong>$info->type</strong>" : "`$info->type`");
                                break;
                            }
                            foreach ($info->children as $key => $value) {
                                $cv = new ValidationInfo($value);
                                $cv->name = "{$info->name}[$key]";
                                if (array_key_exists($key, $input) || $cv->required) {
                                    $instance->{$key} = static::validate(
                                        Util::nestedValue($input, $key),
                                        $cv
                                    );
                                }
                            }
                        }
                        return $instance;
                    }
            }
            throw new HttpException(400, $error);
        } catch (\Exception $e) {
            static::$exceptions[$info->name] = $e;
            if (static::$holdException) {
                return null;
            }
            throw $e;
        }
    }
}