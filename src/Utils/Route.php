<?php


namespace Luracast\Restler\Utils;


class Route extends ValueObject
{
    const PUBLIC = 0;
    const HYBRID = 1;
    const PROTECTED_BY_COMMENT = 2;
    const PROTECTED_METHOD = 3;
    /**
     * @var string target uri
     */
    public $url;

    /**
     * @var callable
     */
    public $action;

    /**
     * @var array
     */
    public $parameters = [
        //name => [
        //  type     => array,
        //  of       => strings
        //  index    => 0,
        //  default  => '',
        //  nullable => true,
        //  min      => 3,
        //  max      => 10
        //  ...
        //], ...
    ];

    /**
     * @var int access level
     */
    public $access = self::PUBLIC;

    public $requestMediaTypes = ['application/json'];
    public $responseMediaTypes = ['application/json'];

}