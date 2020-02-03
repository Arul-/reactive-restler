<?php


namespace Luracast\Restler\Data;


class Type extends ValueObject
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
     * @var bool can it hold null value?
     */
    public $nullable = true;

    /**
     * @var bool does it hold scalar data or object data
     */
    public $scalar = false;

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
}
