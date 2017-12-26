<?php declare(strict_types=1);


use Luracast\Restler\RestException;

class HttpException extends RestException
{
    private $headers = [];

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