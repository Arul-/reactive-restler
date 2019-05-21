<?php


namespace Luracast\Restler\Data;


use Luracast\Restler\Utils\CommentParser;

class Returns extends ValueObject
{
    /**
     * Data type of the variable being validated.
     * It will be mostly string
     *
     * @var string|array multiple types are specified it will be of
     *      type array otherwise it will be a string
     */
    public $type = 'array';

    /**
     * When the type is array, this field is used to define the type of the
     * contents of the array
     *
     * @var string|null when all the items in an array are of certain type, we
     *      can set this property. It will be null if the items can be of any type
     */
    public $contentType;

    /**
     * @var array of children to be validated
     */
    public $children = null;

    /**
     * @var string
     */
    public $description = '';

    public static function parse(array $properties): self
    {
        $p2 = $properties[CommentParser::$embeddedDataName] ?? [];
        unset($properties[CommentParser::$embeddedDataName]);
        if (isset($p2['type'])) {
            $p2['contentType'] = $p2['type'];
            unset($p2['type']);
        }

        $instance = new static();
        $instance->applyProperties($properties);
        $instance->applyProperties($p2);
        return $instance;
    }

}