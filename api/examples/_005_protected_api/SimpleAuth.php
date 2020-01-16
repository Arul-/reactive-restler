<?php

use Luracast\Restler\Contracts\AuthenticationInterface;
use Luracast\Restler\Contracts\ExplorableAuthenticationInterface;
use Luracast\Restler\Contracts\SelectivePathsInterface;
use Luracast\Restler\Contracts\SelectivePathsTrait;
use Luracast\Restler\OpenApi3\Security\ApiKeyAuth;
use Luracast\Restler\OpenApi3\Security\Scheme;
use Luracast\Restler\ResponseHeaders;
use Psr\Http\Message\ServerRequestInterface;

class SimpleAuth implements ExplorableAuthenticationInterface, SelectivePathsInterface
{
    use SelectivePathsTrait;

    const KEY = 'rEsTlEr4';

    function key()
    {
        return SimpleAuth::KEY;
    }

    public function _isAllowed(ServerRequestInterface $request, ResponseHeaders $responseHeaders): bool
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

    public static function scheme(): Scheme
    {
        return new ApiKeyAuth('key', ApiKeyAuth::IN_QUERY);
    }
}
