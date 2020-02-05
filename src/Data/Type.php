<?php


namespace Luracast\Restler\Data;


class Type extends ValueObject
{
    /**
     * Data type of the variable being validated.
     * It will be mostly string
     *
     * @var string only single type is allowed. if multiple types are specified,
     * Restler will pick the first. if null is one of the values, it will be simple set the nullable flag
     * if multiple is true, type denotes the content type here
     */
    public string $type = 'string';

    /**
     * @var bool is it a list?
     */
    public bool $multiple = false;

    /**
     * @var bool can it hold null value?
     */
    public bool $nullable = true;

    /**
     * @var bool does it hold scalar data or object data
     */
    public bool $scalar = false;

    /**
     * @var string|null if the given data can be classified to sub types it will be specified here
     */
    public ?string $format;

    /**
     * @var array|null of children to be validated. used only for non scalar type
     */
    public ?array $children = null;

    /**
     * @var string
     */
    public string $description = '';
}
