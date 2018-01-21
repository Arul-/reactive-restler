<?php namespace Luracast\Restler\Exceptions;


class Redirect extends HttpException
{
    public function __construct(string $location, array $queryParams = [], int $httpStatusCode = 302)
    {
        parent::__construct($httpStatusCode);
        if (!empty($queryParams)) {
            $location .= '?' . http_build_query($queryParams);
        }
        $this->setHeader('Location', $location);
    }
}