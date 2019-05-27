<?php


namespace Luracast\Restler\OpenApi3\Security;


use InvalidArgumentException;

class ApiKeyAuth extends Scheme
{
    const IN_HEADER = 'header';
    const IN_QUERY = 'query';
    const IN_COOKIE = 'cookie';

    protected $type = Scheme::TYPE_API_KEY;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var string
     */
    protected $in;

    /**
     * ApiKeyAuth constructor.
     * @param string $name
     * @param string $in {@choice header,query,cookie}
     * @param string $description
     */
    public function __construct(string $name, string $in = 'header', string $description = '')
    {
        if (!defined(__CLASS__ . '::IN_' . strtoupper($in))) {
            throw new InvalidArgumentException('value for $in should be one of the class constants');
        }
        $this->name = $name;
        $this->in = $in;
        $this->description = $description;
    }
}