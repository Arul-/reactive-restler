<?php

use Luracast\Restler\Contracts\AuthenticationInterface;
use Luracast\Restler\Contracts\SelectivePathsInterface;
use Luracast\Restler\Contracts\SelectivePathsTrait;
use Luracast\Restler\Contracts\UserIdentificationInterface;
use Luracast\Restler\ResponseHeaders;
use Luracast\Restler\Utils\ClassName;
use Psr\Http\Message\ServerRequestInterface;

class KeyAuth implements AuthenticationInterface, SelectivePathsInterface
{
    use SelectivePathsTrait;

    public function _isAllowed(ServerRequestInterface $request, ResponseHeaders $responseHeaders): bool
    {
        $query = $request->getQueryParams();
        $allowed = isset($query['api_key']) && $query['api_key'] == 'r3rocks';
        if ($allowed) {
            // if api key is unique for each user
            // we can use that to identify and track the user
            // for rate limiting and more
            /** @var UserIdentificationInterface $user */
            $user = ClassName::get(UserIdentificationInterface::class);
            $user::setUniqueIdentifier($query['api_key']);
        }
        return $allowed;
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
