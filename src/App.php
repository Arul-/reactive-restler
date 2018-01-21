<?php namespace Luracast\Restler;

use Luracast\Restler\Cache\HumanReadableCache;
use Luracast\Restler\Contracts\{
    AccessControlInterface, AuthenticationInterface, CacheInterface, ComposerInterface, FilterInterface, RequestMediaTypeInterface, ResponseMediaTypeInterface, UserIdentificationInterface, ValidationInterface
};
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Filters\RateLimiter;
use Luracast\Restler\MediaTypes\{
    Amf, Csv, Js, Json, Plist, Tsv, Upload, UrlEncoded, Xml, Yaml
};
use Luracast\Restler\Utils\Validator;
use Psr\{
    Http\Message\ResponseInterface, Http\Message\ServerRequestInterface
};
use React\Http\Io\ServerRequest;
use React\Http\Response;

class App
{
    public static $productionMode = false;
    /**
     * @var string full path of the directory where all the generated files will
     * be kept. When set to null (default) it will use the cache folder that is
     * in the same folder as index.php (gateway)
     */
    public static $cacheDirectory;

    // ==================================================================
    //
    // Routing
    //
    // ------------------------------------------------------------------

    /**
     * @var bool should auto routing for public and protected api methods
     * should be enabled by default or not. Set this to false to get
     * Restler 1.0 style behavior
     */
    public static $autoRoutingEnabled = true;

    /**
     * @var boolean avoids creating multiple routes that can increase the
     * ambiguity when set to true. when a method parameter is optional it is
     * not mapped to the url and should only be used in request body or as
     * query string `/resource?id=value`. When a parameter is required and is
     * scalar, it will be mapped as part of the url `/resource/{id}`
     */
    public static $smartAutoRouting = true;

    /**
     * @var boolean enables more ways of finding the parameter data in the request.
     * If you need backward compatibility with Restler 2 or below turn this off
     */
    public static $smartParameterParsing = true;

    // ==================================================================
    //
    // API Version Management
    //
    // ------------------------------------------------------------------

    /**
     * @var null|string name that is used for vendor specific media type and
     * api version using the Accept Header for example
     * application/vnd.{vendor}-v1+json
     *
     * Keep this null if you do not want to use vendor MIME for specifying api version
     */
    public static $apiVendor = null;

    /**
     * @var bool set it to true to force vendor specific MIME for versioning.
     * It will be automatically set to true when Defaults::$vendor is not
     * null and client is requesting for the custom MIME type
     */
    public static $useVendorMIMEVersioning = false;

    /**
     * @var bool set it to true to use enableUrl based versioning
     */
    public static $useUrlBasedVersioning = false;

    // ==================================================================
    //
    // Request
    //
    // ------------------------------------------------------------------

    /**
     * @var string name to be used for the method parameter to capture the
     *             entire request data
     */
    public static $fullRequestDataName = 'request_data';

    /**
     * @var string name of the property that can sent through $_GET or $_POST to
     *             override the http method of the request. Set it to null or
     *             blank string to disable http method override through request
     *             parameters.
     */
    public static $httpMethodOverrideProperty = 'http_method';

    /**
     * @var bool should auto validating api parameters should be enabled by
     *           default or not. Set this to false to avoid validation.
     */
    public static $autoValidationEnabled = true;

    // ==================================================================
    //
    // Response
    //
    // ------------------------------------------------------------------

    /**
     * @var bool HTTP status codes are set on all responses by default.
     * Some clients (like flash, mobile) have trouble dealing with non-200
     * status codes on error responses.
     *
     * You can set it to true to force a HTTP 200 status code on all responses,
     * even when errors occur. If you suppress status codes, look for an error
     * response to determine if an error occurred.
     */
    public static $suppressResponseCode = false;

    public static $supportedCharsets = array('utf-8', 'iso-8859-1');
    public static $supportedLanguages = array('en', 'en-US');

    public static $charset = 'utf-8';
    public static $language = 'en';

    /**
     * @var bool when set to true, it will exclude the response body
     */
    public static $emptyBodyForNullResponse = true;

    /**
     * @var bool when set to true, the response will not be outputted directly into the buffer.
     * If set, Restler::handle() will return the response as a string.
     */
    public static $returnResponse = false;

    /**
     * @var bool enables CORS support
     */
    public static $crossOriginResourceSharing = false;
    public static $accessControlAllowOrigin = '*';
    public static $accessControlAllowMethods =
        'GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD';

    // ==================================================================
    //
    // Header
    //
    // ------------------------------------------------------------------

    /**
     * @var array default Cache-Control template that used to set the
     * Cache-Control header and has two values, first one is used when
     * App::$headerExpires is 0 and second one when it has some time
     * value specified. When only one value is specified it will be used for
     * both cases
     */
    public static $headerCacheControl = array(
        'no-cache, must-revalidate',

        /* "public, " or "private, " will be prepended based on api method
         * called (public or protected)
         */
        'max-age={expires}, must-revalidate',

    );

    /**
     * @var int sets the content to expire immediately when set to zero
     * alternatively you can specify the number of seconds the content will
     * expire. This setting can be altered at api level using php doc comment
     * with @expires numOfSeconds
     */
    public static $headerExpires = 0;

    // ==================================================================
    //
    // Access Control
    //
    // ------------------------------------------------------------------

    /**
     * @var int set the default api access mode
     *      value of 0 = public api
     *      value of 1 = hybrid api using `@access hybrid` comment
     *      value of 2 = protected api using `@access protected` comment
     *      value of 3 = protected api using `protected function` method
     */
    public static $apiAccessLevel = 0;

    /**
     * @var int time in milliseconds for bandwidth throttling,
     * which is the minimum response time for each api request. You can
     * change it per api method by setting `@throttle 3000` in php doc
     * comment either at the method level or class level
     */
    public static $throttle = 0;

// ==================================================================
    //
    // Overrides for API User
    //
    // ------------------------------------------------------------------

    /**
     * @var array determines what are the query string names that will
     * override the properties here with their values
     */
    public static $fromQuery = [
        /**
         * suppress_response_codes=true as an URL parameter to force
         * a HTTP 200 status code on all responses
         */
        'suppress_response_codes' => 'suppressResponseCode',
    ];

    /**
     * @var array contains validation details for defaults to be used when
     * set through URL parameters
     */
    public static $propertyValidations = [
        'suppressResponseCode' => ['type' => 'bool'],
        'headerExpires' => ['type' => 'int', 'min' => 0],
    ];

    // ==================================================================
    //
    // Overrides API Developer
    //
    // ------------------------------------------------------------------

    /**
     * @var array determines what are the phpdoc comment tags that will
     * override the Defaults here with their values
     */
    public static $fromComments = [

        /**
         * use PHPDoc comments such as the following
         * `@cache no-cache, must-revalidate` to set the Cache-Control header
         *        for a specific api method
         */
        'cache' => 'headerCacheControl',

        /**
         * use PHPDoc comments such as the following
         * `@expires 50` to set the Expires header
         *          for a specific api method
         */
        'expires' => 'headerExpires',

        /**
         * use PHPDoc comments such as the following
         * `@throttle 300`
         *           to set the bandwidth throttling for 300 milliseconds
         *           for a specific api method
         */
        'throttle' => 'throttle',

        /**
         * enable or disable smart auto routing from method comments
         * this one is hardwired so cant be turned off
         * it is placed here just for documentation purpose
         */
        'smart-auto-routing' => 'smartAutoRouting',
    ];
    /**
     * Implementations
     *
     * Interfaces and a list of known implementing classes
     *
     * @var array {@type associative}
     */
    public static $implementations = [
        CacheInterface::class => [HumanReadableCache::class],
        ValidationInterface::class => [Validator::class],
        UserIdentificationInterface::class => [UserIdentifier::class],
        AccessControlInterface::class => [ /* YOUR_CLASS_NAME_HERE */],
        AuthenticationInterface::class => [ /* YOUR_CLASS_NAME_HERE */],
        ComposerInterface::class => [Composer::class],
        FilterInterface::class => [RateLimiter::class],
        RequestMediaTypeInterface::class => [Json::class],
        ResponseMediaTypeInterface::class => [Json::class],
        ServerRequestInterface::class => [ServerRequest::class],
        ResponseInterface::class => [Response::class],
    ];
    /**
     * Class Aliases
     *
     * Shortcut names for classes
     *
     * @var array {@type associative}
     */
    public static $aliases = [
        // Core
        'Application' => Reactler::class,
        // Formats
        'Amf' => Amf::class,
        'Csv' => Csv::class,
        'Js' => Js::class,
        'Json' => Json::class,
        'Plist' => Plist::class,
        'Tsv' => Tsv::class,
        'Upload' => Upload::class,
        'UrlEncoded' => UrlEncoded::class,
        'Xml' => Xml::class,
        'Yaml' => Yaml::class,
        //Filters,
        'RateLimiter' => RateLimiter::class,
        // Exception
        'HttpException' => HttpException::class,
        // Backward Compatibility
        'RestException' => HttpException::class,
        'Restler' => Reactler::class,
        'JsonFormat' => Json::class,
        'JsFormat' => Js::class,
        'XmlFormat' => Xml::class,
    ];
}