<?php

namespace Auth;

use Luracast\Restler\Contracts\AuthenticationInterface;
use OAuth2\Convert;
use OAuth2\GrantType\AuthorizationCode;
use OAuth2\GrantType\UserCredentials;
use OAuth2\Request;
use OAuth2\Response;
use OAuth2\Server as OAuth2Server;
use OAuth2\Storage\Pdo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


/**
 * Class Server
 *
 * @package OAuth2
 *
 */
class Server implements AuthenticationInterface
{
    /**
     * @var OAuth2Server
     */
    protected static $server;
    /**
     * @var Pdo
     */
    protected static $storage;
    /**
     * @var Request
     */
    protected $request;

    public function __construct(ServerRequestInterface $psrRequest)
    {
        $dir = __DIR__ . '/db/';
        $file = 'oauth.sqlite';
        if (!file_exists($dir . $file)) {
            include_once $dir . 'rebuild_db.php';
        }
        static::$storage = new Pdo(
            array('dsn' => 'sqlite:' . $dir . $file)
        );
        // create array of supported grant types
        $grantTypes = array(
            'authorization_code' => new AuthorizationCode(static::$storage),
            'user_credentials' => new UserCredentials(static::$storage),
        );
        $this->request = Convert::fromPSR7($psrRequest);
        static::$server = new OAuth2Server(
            static::$storage,
            array('enforce_state' => true, 'allow_implicit' => true),
            $grantTypes
        );
    }

    /**
     * Stage 1: Client sends the user to this page
     *
     * User responds by accepting or denying
     *
     * @view oauth2/server/authorize.twig
     * @format Html
     */
    public function authorize()
    {
        // validate the authorize request.  if it is invalid,
        // redirect back to the client with the errors in tow
        if (!static::$server->validateAuthorizeRequest($this->request)) {
            return Convert::toPSR7(static::$server->getResponse());
        }
        return array('queryString' => http_build_query($this->request->getAllQueryParameters()));
    }

    /**
     * Stage 2: User response is captured here
     *
     * Success or failure is communicated back to the Client using the redirect
     * url provided by the client
     *
     * On success authorization code is sent along
     *
     *
     * @param bool $authorize
     *
     * @return ResponseInterface
     *
     * @format Json,Upload
     */
    public function postAuthorize($authorize = false)
    {
        /** @var Response $response */
        $response = static::$server->handleAuthorizeRequest(
            $this->request,
            new Response(),
            (bool)$authorize
        );
        return Convert::toPSR7($response);
    }

    /**
     * Stage 3: Client directly calls this api to exchange access token
     *
     * It can then use this access token to make calls to protected api
     *
     * @format Json,Upload
     */
    public function postGrant()
    {
        /** @var Response $response */
        $response = static::$server->handleTokenRequest($this->request);
        return Convert::toPSR7($response);
    }

    /**
     * Sample api protected with OAuth2
     *
     * For testing the oAuth token
     *
     * @access protected
     */
    public function access()
    {
        return array(
            'friends' => array('john', 'matt', 'jane')
        );
    }


    public static function getWWWAuthenticateString(): string
    {
        return 'Bearer realm="example"';
    }


    /**
     * Access verification method.
     *
     * API access will be denied when this method returns false
     *
     *
     * @param ServerRequestInterface $request
     *
     * @param array $responseHeaders
     * @return boolean true when api access is allowed false otherwise
     *
     */
    public function _isAllowed(ServerRequestInterface $request, array &$responseHeaders): bool
    {
        $request = Convert::fromPSR7($request);
        return self::$server->verifyResourceRequest($request);
    }
}