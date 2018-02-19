<?php namespace Luracast\Restler\Contracts;

use Luracast\Restler\Exceptions\HttpException;
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
     * @param array $responseHeaders
     * @return boolean true when api access is allowed false otherwise
     *
     * @throws HttpException
     */
    public function __isAllowed(ServerRequestInterface $request, array &$responseHeaders): bool;
}