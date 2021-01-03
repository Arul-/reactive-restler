<?php


namespace Luracast\Restler\Auth;


use Firebase\JWT\JWT;
use Luracast\Restler\Contracts\DependentTrait;
use Luracast\Restler\Contracts\ExplorableAuthenticationInterface;
use Luracast\Restler\Contracts\SelectivePathsInterface;
use Luracast\Restler\Contracts\SelectivePathsTrait;
use Luracast\Restler\Contracts\UserIdentificationInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\OpenApi3\Security\BearerAuth;
use Luracast\Restler\OpenApi3\Security\Scheme;
use Luracast\Restler\ResponseHeaders;
use Luracast\Restler\Utils\ClassName;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use function preg_replace;
use function trim;

class JsonWebToken implements ExplorableAuthenticationInterface, SelectivePathsInterface
{
    use DependentTrait;
    use SelectivePathsTrait;

    public static $publicKey = '';
    public static $userIdentifierProperty = 'sub';

    /** @var null|string Issuer */
    public static $matchedIssuer = null;
    /** @var null|string Audience */
    public static $matchedAudience = null;
    /** @var null|string Authorized party - the party to which the ID Token was issued */
    public static $matchedAuthorizedParty = null;
    /** @var null|string Client Identifier */
    public static $matchedClientIdentifier = null;

    public $token;

    /**
     * WebToken constructor.
     * @throws HttpException
     */
    public function __construct()
    {
        static::checkDependencies();
    }

    public static function getWWWAuthenticateString(): string
    {
        return 'Bearer realm="Access to API"';
    }

    public static function scheme(): Scheme
    {
        return new BearerAuth('JWT', 'Json Web Token');
    }

    public static function dependencies(): array
    {
        return [
            //CLASS_NAME => vendor/project:version
            JWT::class => 'firebase/php-jwt:^5.2'
        ];
    }

    public function _isAllowed(ServerRequestInterface $request, ResponseHeaders $responseHeaders): bool
    {
        if ($request->hasHeader('authorization') === false) {
            return false;
        }
        try {
            $header = $request->getHeaderLine('authorization');
            $jwt = trim((string)preg_replace('/^(?:\s+)?Bearer\s/', '', $header));
            $this->token = $token = JWT::decode($jwt, static::publicKey(), ['RS256']);
            if (static::$matchedIssuer && static::$matchedIssuer !== ($token->{'iss'} ?? null)) {
                $this->accessDenied('Invalid Issuer.');
            }
            if (static::$matchedAudience && static::$matchedAudience !== ($token->{'aud'} ?? null)) {
                $this->accessDenied('Invalid Audience.');
            }
            if (static::$matchedAuthorizedParty && static::$matchedAuthorizedParty !== ($token->{'azp'} ?? null)) {
                $this->accessDenied('Invalid Authorized Party.');
            }
            if (static::$matchedClient && static::$matchedClient !== ($token->{'client_id'} ?? null)) {
                $this->accessDenied('Invalid Client Identifier.');
            }
            if ($id = ($token->{static::$userIdentifierProperty} ?? null)) {
                $this->accessDenied('Invalid User Identifier.');
            };
            /** @var UserIdentificationInterface $userClass */
            $userClass = ClassName::get(UserIdentificationInterface::class);
            $userClass::setUniqueIdentifier($id);
            return true;
        } catch (HttpException $httpException) {
            throw $httpException;
        } catch (Throwable $throwable) {
            $this->accessDenied($throwable->getMessage(), $throwable);
        }
    }

    protected static function publicKey(): string
    {
        if (empty(self::$publicKey)) {
            throw new HttpException(500, '`' . static::class . '::$publicKey` is needed for token verification');
        }
        $start = "-----BEGIN PUBLIC KEY-----\n";
        if (0 === strpos(static::$publicKey, $start)) {
            return static::$publicKey;
        }
        return sprintf("%s%s\n-----END PUBLIC KEY-----", $start, wordwrap(
            static::$publicKey,
            64,
            "\n",
            true
        ));
    }

    /**
     * @param string $reason
     * @param ?Throwable $previous
     * @throws HttpException 403 Access Denied
     */
    protected function accessDenied(string $reason, ?Throwable $previous = null)
    {
        throw new HttpException(403, 'Access Denied. ' . $reason, [], $previous);
    }
}
