<?php namespace Luracast\Restler\Exceptions;


use Luracast\Restler\Router;
use Luracast\Restler\Utils\Text;

class Redirect extends HttpException
{
    public function __construct(string $location, array $queryParams = [], int $httpStatusCode = 302)
    {
        parent::__construct($httpStatusCode);
        if (!empty($queryParams)) {
            $location .= '?' . http_build_query($queryParams);
        }
        if (!Text::beginsWith($location, 'http') && !Text::beginsWith($location, '/')) {
            $location = Router::getBasePath() . '/' . $location;
        }
        $this->setHeader('Location', $location);
    }
}