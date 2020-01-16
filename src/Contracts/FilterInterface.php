<?php namespace Luracast\Restler\Contracts;

use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\ResponseHeaders;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface for creating classes that perform authentication/access
 * verification
 *
 * @package Luracast\Restler\Contracts
 */
interface FilterInterface
{
    /**
     * Access verification method.
     *
     * API access will be denied when this method returns false
     *
     * @abstract
     *
     * @param ServerRequestInterface $request
     *
     * @param ResponseHeaders $responseHeaders
     * @return boolean true when api access is allowed false otherwise
     *
     * @throws HttpException
     */
    public function _isAllowed(ServerRequestInterface $request, ResponseHeaders $responseHeaders): bool;
}
