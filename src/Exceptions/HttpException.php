<?php namespace Luracast\Restler\Exceptions;


use Luracast\Restler\RestException;

class HttpException extends RestException
{
    private $headers = [];
    public $emptyMessageBody = false;

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }
}