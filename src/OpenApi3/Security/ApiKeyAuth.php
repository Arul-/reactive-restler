<?php


namespace Luracast\Restler\OpenApi3\Security;


use InvalidArgumentException;

class ApiKeyAuth extends Scheme
{
    const IN_HEADER = 'header';
    const IN_QUERY = 'query';
    const IN_COOKIE = 'cookie';

    protected $type = 'apiKey';
    protected $in;
    protected $name;

    /**
     * ApiKeyAuth constructor.
     * @param string $name
     * @param string $in {@choice header,query,cookie}
     */
    public function __construct(string $name, string $in = 'header')
    {
        if (!defined(__CLASS__ . '::IN_' . strtoupper($in))) {
            throw new InvalidArgumentException('value for $in should be one of the class constants');
        }
        $this->name = $name;
        $this->in = $in;
    }
}