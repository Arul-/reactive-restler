<?php

use Luracast\Restler\Contracts\AuthenticationInterface;
use Psr\Http\Message\ServerRequestInterface;

class SimpleAuth implements AuthenticationInterface
{
    const KEY = 'rEsTlEr2';

    function key()
    {
        return SimpleAuth::KEY;
    }

    public function __isAllowed(ServerRequestInterface $request): bool
    {
        $query = $request->getQueryParams();
        return isset($query['key']) && $query['key'] == SimpleAuth::KEY ? true : false;
    }

    /**
     * @return string string to be used with WWW-Authenticate header
     * @example Basic
     * @example Digest
     * @example OAuth
     */
    public function __getWWWAuthenticateString(): string
    {
        return 'Query name="key"';
    }

}