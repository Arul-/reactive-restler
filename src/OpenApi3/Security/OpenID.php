<?php


namespace Luracast\Restler\OpenApi3\Security;


class OpenID extends Scheme
{
    protected $type = 'openIdConnect';
    /**
     * @var string
     */
    protected $connectUrl;

    public function __construct(string $connectUrl)
    {
        $this->connectUrl = $connectUrl;
    }
}