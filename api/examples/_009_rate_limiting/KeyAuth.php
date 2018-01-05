<?php

use Luracast\Restler\Contracts\AuthenticationInterface;
use Psr\Http\Message\ServerRequestInterface;

class KeyAuth implements AuthenticationInterface
{
    public function __isAllowed(ServerRequestInterface $request): bool
    {
        $query = $request->getQueryParams();
        return isset($query['api_key']) && $query['api_key'] == 'r3rocks';
    }

    /**
     * @return string string to be used with WWW-Authenticate header
     * @example Basic
     * @example Digest
     * @example OAuth
     */
    public static function __getWWWAuthenticateString(): string
    {
        return 'Query name="api_key"';
    }
}
