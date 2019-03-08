<?php

namespace Auth;

use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Restler;
use Luracast\Restler\Session;
use Luracast\Restler\StaticProperties;
use Luracast\Restler\Utils\ClassName;
use Psr\Http\Message\ResponseInterface;

class Client
{
    /**
     * @var string url of the OAuth2 server to authorize
     */
    public static $serverUrl;
    public static $authorizeRoute = 'authorize';
    public static $tokenRoute = 'grant';
    public static $resourceMethod = 'GET';
    public static $resourceRoute = 'access';
    public static $resourceParams = array();
    public static $resourceOptions = array();
    public static $clientId = 'demoapp';
    public static $clientSecret = 'demopass';
    /**
     * @var string where to send the OAuth2 authorization result
     * (success or failure)
     */
    protected static $replyBackUrl;
    /**
     * @var Restler
     */
    private $restler;
    /**
     * @var StaticProperties
     */
    private $html;

    public function __construct(Restler $restler, Session $session, StaticProperties $html)
    {
        $this->restler = $restler;
        $this->html = $html;
        $session->start();
        $this->html->data['session_id'] = $session->getId();
        if (!static::$serverUrl) {
            $base = (string)$this->restler->baseUrl . '/examples/';
            static::$serverUrl =
                $base . '_015_oauth2_server';
            static::$replyBackUrl = $base . '_014_oauth2_client/authorized';
            static::$authorizeRoute = static::fullURL(static::$authorizeRoute);
            static::$tokenRoute = static::fullURL(static::$tokenRoute);
            static::$resourceRoute = static::fullURL(static::$resourceRoute);
        }
    }

    /**
     * Prefix server url if relative path is used
     *
     * @param string $path full url or relative path
     * @return string proper url
     */
    private function fullURL($path)
    {
        return 0 === strpos($path, 'http')
            ? $path
            : static::$serverUrl . '/' . $path;
    }

    /**
     * Stage 1: Let user start the oAuth process by clicking on the button
     *
     * He will then be taken to the oAuth server to grant or deny permission
     *
     * @format Html
     * @view   oauth2/client/index.twig
     */
    public function index()
    {
        return array(
            'authorize_url' => static::$authorizeRoute,
            'authorize_redirect_url' => static::$replyBackUrl
        );
    }

    /**
     * Stage 2: Users response is recorded by the server
     *
     * Server redirects the user back with the result.
     *
     * If successful,
     *
     * Client exchanges the authorization code by a direct call (not through
     * user's browser) to get access token which can then be used call protected
     * APIs, if completed it calls a protected api and displays the result
     * otherwise client ends up showing the error message
     *
     * Else
     *
     * Client renders the error message to the user
     *
     * @param string $code
     * @param string $error_description
     * @param string $error_uri
     *
     * @return array
     *
     * @format Html
     */
    public function authorized(
        $code = null,
        $error_description = null,
        $error_uri = null
    ) {
        // the user denied the authorization request
        if (!$code) {
            $this->html->view = 'oauth2/client/denied.twig';
            return array('error' => compact('error_description', 'error_uri'));
        }
        // exchange authorization code for access token
        $query = array(
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => static::$clientId,
            'client_secret' => static::$clientSecret,
            'redirect_uri' => static::$replyBackUrl,
        );
        //
        $param = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($query)
            ]
        ];
        $context = stream_context_create($param);
        $fp = fopen(static::$tokenRoute, 'rb', false, $context);
        $response = stream_get_contents($fp);
        fclose($fp);
        $response = json_decode($response,true);
        // if it is successful, call the API with the retrieved token
        if (($token = $response['access_token'] ?? null)) {
            // make request to the API for awesome data
            $data = static::$resourceParams + ['access_token' => $token];
            $param['http']['content'] = http_build_query($data);
            $context2 = stream_context_create($param);
            $fp2 = fopen(static::$resourceRoute, 'rb', false, $context2);
            $response = stream_get_contents($fp2);
            $this->html->view = 'oauth2/client/granted.twig';
            return array(
                    'token' => $token,
                    'endpoint' => static::$resourceRoute . '?' . http_build_query($data)
                ) + json_decode($response, true);
        }
        $this->html->view = 'oauth2/client/error.twig';
        //return ['error' => $error];
        return ['error' => 'server call failed'];
    }
}
