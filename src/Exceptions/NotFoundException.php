<?php namespace Luracast\Restler\Exceptions;


use Luracast\Restler\Exceptions\HttpException;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends HttpException implements NotFoundExceptionInterface
{
    public function __construct(?string $errorMessage = null, array $details = array(), $previous = null)
    {
        parent::__construct(404, $errorMessage, $details, $previous);
    }
}