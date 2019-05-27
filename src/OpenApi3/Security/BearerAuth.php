<?php


namespace Luracast\Restler\OpenApi3\Security;


class BearerAuth extends Scheme
{
    protected $type = Scheme::TYPE_HTTP;
    protected $scheme = Scheme::HTTP_SCHEME_BEARER;
    /**
     * @var string
     */
    private $bearerFormat;

    public function __construct(string $bearerFormat, string $description = '')
    {
        $this->bearerFormat = $bearerFormat;
        $this->description = $description;
    }
}