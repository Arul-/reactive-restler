<?php namespace Luracast\Restler\Contracts;

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
     * @return boolean true when api access is allowed false otherwise
     */
    public function __isAllowed(ServerRequestInterface $request): bool;
}