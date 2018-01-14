<?php

use Luracast\Restler\Contracts\AuthenticationInterface;
use Luracast\Restler\Contracts\SelectivePathsInterface;
use Luracast\Restler\Contracts\SelectivePathsTrait;
use Psr\Http\Message\ServerRequestInterface;

class SimpleAuth implements AuthenticationInterface, SelectivePathsInterface
{
    use SelectivePathsTrait;

    const KEY = 'rEsTlEr2';

    function key()
    {
        return SimpleAuth::KEY;
    }

    public function __isAllowed(ServerRequestInterface $request, array &$responseHeaders = []): bool
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
    public static function getWWWAuthenticateString(): string
    {
        return 'Query name="key"';
    }

}