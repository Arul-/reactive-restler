<?php

use Luracast\Restler\Contracts\AuthenticationInterface;
use Luracast\Restler\Contracts\SelectivePathsInterface;
use Luracast\Restler\Contracts\SelectivePathsTrait;
use Psr\Http\Message\ServerRequestInterface;

class KeyAuth implements AuthenticationInterface, SelectivePathsInterface
{
    use SelectivePathsTrait;

    public function __isAllowed(ServerRequestInterface $request, array &$responseHeaders = []): bool
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
    public static function getWWWAuthenticateString(): string
    {
        return 'Query name="api_key"';
    }
}
