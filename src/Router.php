<?php namespace Luracast\Restler;


use Exception;
use Luracast\Restler\Contracts\AccessControlInterface;
use Luracast\Restler\Contracts\AuthenticationInterface;
use Luracast\Restler\Contracts\FilterInterface;
use Luracast\Restler\Contracts\MediaTypeInterface;
use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Contracts\UsesAuthenticationInterface;
use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\Data\Text;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

class Router
{
    public static $prefixingParameterNames = [
        'id'
    ];

    public static $fieldTypesByName = [
        'email' => 'email',
        'password' => 'password',
        'phone' => 'tel',
        'mobile' => 'tel',
        'tel' => 'tel',
        'search' => 'search',
        'date' => 'date',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
        'url' => 'url',
        'link' => 'url',
        'href' => 'url',
        'website' => 'url',
        'color' => 'color',
        'colour' => 'color',
    ];

    public static $authClasses = [];
    public static $preAuthFilterClasses = [];
    public static $postAuthFilterClasses = [];
    public static $formatMap = ['extensions' => []];
    public static $formatOverridesMap = ['extensions' => []];
    public static $versionMap = [];

    public static $minimumVersion = 1;
    public static $maximumVersion = 1;

    public static $readableMediaTypes = [];
    public static $writableMediaTypes = [];

    protected static $routes = [];
    protected static $models = [];

    public static function setApiVersion(int $maximum = 1, int $minimum = 1)
    {
        static::$maximumVersion = $maximum;
        static::$minimumVersion = $minimum;
    }

    /**
     * @param string[] ...$types
     * @throws Exception
     */
    public static function setMediaTypes(string ...$types): void
    {
        $extensions = [];
        static::$writableMediaTypes = static::$readableMediaTypes = [];
        static::$formatMap = [];
        foreach ($types as $type) {
            $implements = class_implements($type);
            if (!isset($implements[MediaTypeInterface::class])) {
                throw new Exception($type . ' is an invalid media type class; it must implement ' .
                    'MediaTypeInterface interface');
            }
            $either = false;
            foreach ($type::supportedMediaTypes() as $mime => $extension) {
                if ($implements[ResponseMediaTypeInterface::class]) {
                    $either = true;
                    static::$writableMediaTypes[] = $mime;
                    $extensions[".$extension"] = true;
                    if (!isset(static::$formatMap['default'])) {
                        static::$formatMap['default'] = $type;
                    }
                    if (!isset(static::$formatMap[$extension])) {
                        static::$formatMap[$extension] = $type;
                    }
                    if (!isset(static::$formatMap[$mime])) {
                        static::$formatMap[$mime] = $type;
                    }
                }
                if ($implements[RequestMediaTypeInterface::class]) {
                    $either = true;
                    static::$readableMediaTypes[] = $mime;
                    if (!isset(static::$formatMap[$mime])) {
                        static::$formatMap[$mime] = $type;
                    }
                }
            }
            if (!$either) {
                throw new Exception($type . ' is an invalid media type class; it must implement ' .
                    'either RequestMediaTypeInterface or ResponseMediaTypeInterface interface');
            }
        }
        static::$formatMap['extensions'] = array_keys($extensions);
    }

    /**
     * @param string[] ...$types
     * @throws Exception
     */
    public static function setRequestMediaTypes(string ...$types): void
    {
        static::$readableMediaTypes = [];
        foreach ($types as $type) {
            if (!isset(class_implements($type)[RequestMediaTypeInterface::class])) {
                throw new Exception($type . ' is an invalid media type class; it must implement ' .
                    'RequestMediaTypeInterface interface');
            }
            foreach ($type::supportedMediaTypes() as $mime => $extension) {
                static::$readableMediaTypes[] = $mime;
                if (!isset(static::$formatMap[$mime])) {
                    static::$formatMap[$mime] = $type;
                }
            }
        }
    }

    /**
     * @param string[] ...$types
     * @throws Exception
     */
    public static function setResponseMediaTypes(string ...$types): void
    {
        static::$writableMediaTypes = [];
        static::_setResponseMediaTypes($types, static::$formatMap, static::$writableMediaTypes);
    }

    /**
     * @param string[] ...$types
     * @throws Exception
     */
    public static function setOverridingResponseMediaTypes(string ...$types): void
    {
        $ignore = [];
        static::_setResponseMediaTypes($types, static::$formatOverridesMap, $ignore);
    }

    /**
     * @param array $types
     * @param array $formatMap
     * @param array $writableMediaTypes
     * @throws Exception
     * @internal
     */
    public static function _setResponseMediaTypes(array $types, array &$formatMap, array &$writableMediaTypes): void
    {
        $extensions = [];
        foreach ($types as $type) {
            if (!isset(class_implements($type)[ResponseMediaTypeInterface::class])) {
                throw new Exception($type . ' is an invalid media type class; it must implement ' .
                    'ResponseMediaTypeInterface interface');
            }
            foreach ($type::supportedMediaTypes() as $mime => $extension) {
                $writableMediaTypes[] = $mime;
                $extensions[".$extension"] = true;
                if (!isset($formatMap[$extension])) {
                    $formatMap[$extension] = $type;
                }
                if (!isset($formatMap[$mime])) {
                    $formatMap[$mime] = $type;
                }
            }
        }
        $formatMap['default'] = $types[0];
        $formatMap['extensions'] = array_keys($extensions);
    }

    /**
     * Add multiple api classes through this method.
     *
     * This method provides better performance when large number
     * of API classes are in use as it processes them all at once,
     * as opposed to hundreds (or more) addAPIClass calls.
     *
     *
     * All the public methods that do not start with _ (underscore)
     * will be will be exposed as the public api by default.
     *
     * All the protected methods that do not start with _ (underscore)
     * will exposed as protected api which will require authentication
     *
     * @param array $map [$resourcePath => $className, $className2 ...]
     *                   array of associative arrays containing the
     *                   class name & optional url prefix for mapping.
     *
     * @throws Exception
     */
    public static function mapApiClasses(array $map): void
    {
        try {
            $maxVersionMethod = '__getMaximumSupportedVersion';
            foreach ($map as $resourcePath => $className) {
                if (is_numeric($resourcePath)) {
                    $resourcePath = strtolower($className);
                }
                if (isset(App::$aliases[$className])) {
                    $className = App::$aliases[$className];
                }
                if (class_exists($className)) {
                    if (method_exists($className, $maxVersionMethod)) {
                        $max = $className::$maxVersionMethod();
                        for ($i = 1; $i <= $max; $i++) {
                            static::$versionMap[$className][$i] = $className;
                        }
                    } else {
                        static::$versionMap[$className][1] = $className;
                    }
                }
                //versioned api
                if (false !== ($index = strrpos($className, '\\'))) {
                    $name = substr($className, 0, $index)
                        . '\\v{$version}' . substr($className, $index);
                } else {
                    if (false !== ($index = strrpos($className, '_'))) {
                        $name = substr($className, 0, $index)
                            . '_v{$version}' . substr($className, $index);
                    } else {
                        $name = 'v{$version}\\' . $className;
                    }
                }

                for ($version = static::$minimumVersion;
                     $version <= static::$maximumVersion;
                     $version++) {

                    $versionedClassName = str_replace('{$version}', $version,
                        $name);
                    if (class_exists($versionedClassName)) {
                        static::addAPIForVersion($versionedClassName,
                            Util::getResourcePath(
                                $className,
                                $resourcePath
                            ),
                            $version
                        );
                        if (method_exists($versionedClassName, $maxVersionMethod)) {
                            $max = $versionedClassName::$maxVersionMethod();
                            for ($i = $version; $i <= $max; $i++) {
                                static::$versionMap[$className][$i] = $versionedClassName;
                            }
                        } else {
                            static::$versionMap[$className][$version] = $versionedClassName;
                        }
                    } elseif (isset(static::$versionMap[$className][$version])) {
                        static::addAPIForVersion(static::$versionMap[$className][$version],
                            Util::getResourcePath(
                                $className,
                                $resourcePath
                            ),
                            $version
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            throw new Exception(
                "mapAPIClasses failed. " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @param string $className
     * @param string|null $resourcePath
     * @throws Exception
     */
    public static function addAPI(string $className, string $resourcePath = null)
    {
        static::mapApiClasses([$resourcePath => $className]);
    }

    /**
     * Route the public and protected methods of an Api class
     *
     * @param string $className
     * @param string $resourcePath
     * @param int $version
     *
     * @throws Exception
     * @throws RestException
     */
    protected static function addAPIForVersion(string $className, string $resourcePath = '', int $version = 1): void
    {

        /*
         * Mapping Rules
         * =============
         *
         * - Optional parameters should not be mapped to URL
         * - If a required parameter is of primitive type
         *      - If one of the self::$prefixingParameterNames
         *              - Map it to URL
         *      - Else If request method is POST/PUT/PATCH
         *              - Map it to body
         *      - Else If request method is GET/DELETE
         *              - Map it to body
         * - If a required parameter is not primitive type
         *      - Do not include it in URL
         */
        $class = new ReflectionClass($className);
        $dataName = CommentParser::$embeddedDataName;
        try {
            $classMetadata = CommentParser::parse($class->getDocComment());
        } catch (Exception $e) {
            throw new RestException(500, "Error while parsing comments of `$className` class. " . $e->getMessage());
        }
        $classMetadata['scope'] = $scope = static::scope($class);
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC +
            ReflectionMethod::IS_PROTECTED);
        foreach ($methods as $method) {
            $methodUrl = strtolower($method->getName());
            //method name should not begin with _
            if ($methodUrl{0} == '_') {
                continue;
            }
            $doc = $method->getDocComment();

            try {
                $metadata = CommentParser::parse($doc) + $classMetadata;
            } catch (Exception $e) {
                throw new RestException(500,
                    "Error while parsing comments of `{$className}::{$method->getName()}` method. " . $e->getMessage());
            }
            //@access should not be private
            if (isset($metadata['access'])
                && $metadata['access'] == 'private'
            ) {
                continue;
            }
            $arguments = array();
            $defaults = array();
            $params = $method->getParameters();
            $position = 0;
            $pathParams = array();
            $allowAmbiguity
                = (isset($metadata['smart-auto-routing'])
                    && $metadata['smart-auto-routing'] != 'true')
                || !Defaults::$smartAutoRouting;
            $metadata['resourcePath'] = trim($resourcePath, '/');
            if (isset($classMetadata['description'])) {
                $metadata['classDescription'] = $classMetadata['description'];
            }
            if (isset($classMetadata['classLongDescription'])) {
                $metadata['classLongDescription']
                    = $classMetadata['longDescription'];
            }
            if (!isset($metadata['param'])) {
                $metadata['param'] = array();
            }
            if (isset($metadata['return']['type'])) {
                if ($qualified = App::resolve($metadata['return']['type'], $scope)) {
                    list($metadata['return']['type'], $metadata['return']['children']) =
                        static::getTypeAndModel(new ReflectionClass($qualified), $scope);
                }
            } else {
                //assume return type is array
                $metadata['return']['type'] = 'array';
            }
            foreach ($params as $param) {
                $children = array();
                $type =
                    $param->isArray() ? 'array' : $param->getClass();
                $arguments[$param->getName()] = $position;
                $defaults[$position] = $param->isDefaultValueAvailable() ?
                    $param->getDefaultValue() : null;
                if (!isset($metadata['param'][$position])) {
                    $metadata['param'][$position] = array();
                }
                $m = &$metadata ['param'] [$position];
                $m ['name'] = $param->getName();
                if (!isset($m[$dataName])) {
                    $m[$dataName] = array();
                }
                $p = &$m[$dataName];
                if (empty($m['label'])) {
                    $m['label'] = Text::title($m['name']);
                }
                if (is_null($type) && isset($m['type'])) {
                    $type = $m['type'];
                }
                if (isset(static::$fieldTypesByName[$m['name']]) && empty($p['type']) && $type == 'string') {
                    $p['type'] = static::$fieldTypesByName[$m['name']];
                }
                $m ['default'] = $defaults [$position];
                $m ['required'] = !$param->isOptional();
                $contentType = Util::nestedValue($p, 'type');
                if ($type == 'array' && $contentType && $qualified = App::resolve($contentType, $scope)) {
                    list($p['type'], $children, $modelName) = static::getTypeAndModel(
                        new ReflectionClass($qualified), $scope,
                        $className . Text::title($methodUrl), $p
                    );
                }
                if ($type instanceof ReflectionClass) {
                    list($type, $children, $modelName) = static::getTypeAndModel($type, $scope,
                        $className . Text::title($methodUrl), $p);
                } elseif ($type && is_string($type) && $qualified = App::resolve($type, $scope)) {
                    list($type, $children, $modelName)
                        = static::getTypeAndModel(new ReflectionClass($qualified), $scope,
                        $className . Text::title($methodUrl), $p);
                }
                if (isset($type)) {
                    $m['type'] = $type;
                }

                $m['children'] = $children;
                if (isset($modelName)) {
                    $m['model'] = $modelName;
                }
                if ($m['name'] == Defaults::$fullRequestDataName) {
                    $from = 'body';
                    if (!isset($m['type'])) {
                        $type = $m['type'] = 'array';
                    }

                } elseif (isset($p['from'])) {
                    $from = $p['from'];
                } else {
                    if ((isset($type) && Util::isObjectOrArray($type))
                    ) {
                        $from = 'body';
                        if (!isset($type)) {
                            $type = $m['type'] = 'array';
                        }
                    } elseif ($m['required'] && in_array($m['name'], static::$prefixingParameterNames)) {
                        $from = 'path';
                    } else {
                        $from = 'body';
                    }
                }
                $p['from'] = $from;
                if (!isset($m['type'])) {
                    $type = $m['type'] = static::type($defaults[$position]);
                }

                if ($allowAmbiguity || $from == 'path') {
                    $pathParams [] = $position;
                }
                $position++;
            }
            $accessLevel = 0;
            if ($method->isProtected()) {
                $accessLevel = 3;
            } elseif (isset($metadata['access'])) {
                if ($metadata['access'] == 'protected') {
                    $accessLevel = 2;
                } elseif ($metadata['access'] == 'hybrid') {
                    $accessLevel = 1;
                }
            } elseif (isset($metadata['protected'])) {
                $accessLevel = 2;
            }
            /*
            echo " access level $accessLevel for $className::"
            .$method->getName().$method->isProtected().PHP_EOL;
            */

            // take note of the order
            $call = array(
                'url' => null,
                'className' => $className,
                'path' => rtrim($resourcePath, '/'),
                'methodName' => $method->getName(),
                'arguments' => $arguments,
                'defaults' => $defaults,
                'metadata' => $metadata,
                'accessLevel' => $accessLevel,
            );
            // if manual route
            if (preg_match_all(
                '/@url\s+(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)'
                . '[ \t]*\/?(\S*)/s',
                $doc, $matches, PREG_SET_ORDER
            )
            ) {
                foreach ($matches as $match) {
                    $httpMethod = $match[1];
                    $url = rtrim($resourcePath . $match[2], '/');
                    //deep copy the call, as it may change for each @url
                    $copy = unserialize(serialize($call));
                    foreach ($copy['metadata']['param'] as $i => $p) {
                        $inPath =
                            strpos($url, '{' . $p['name'] . '}') ||
                            strpos($url, ':' . $p['name']);
                        if ($inPath) {
                            $copy['metadata']['param'][$i][$dataName]['from'] = 'path';
                        } elseif ($httpMethod == 'GET' || $httpMethod == 'DELETE') {
                            $copy['metadata']['param'][$i][$dataName]['from'] = 'query';
                        } elseif (empty($p[$dataName]['from']) || $p[$dataName]['from'] == 'path') {
                            $copy['metadata']['param'][$i][$dataName]['from'] = 'body';
                        }
                    }
                    $url = preg_replace_callback('/{[^}]+}|:[^\/]+/',
                        function ($matches) use ($copy) {
                            $match = trim($matches[0], '{}:');
                            $index = $copy['arguments'][$match];
                            return '{' .
                                static::typeChar(isset(
                                    $copy['metadata']['param'][$index]['type'])
                                    ? $copy['metadata']['param'][$index]['type']
                                    : null)
                                . $index . '}';
                        }, $url);
                    static::addPath($url, $copy, $httpMethod, $version);
                }
                //if auto route enabled, do so
            } elseif (Defaults::$autoRoutingEnabled) {
                // no configuration found so use convention
                if (preg_match_all(
                    '/^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)/i',
                    $methodUrl, $matches)
                ) {
                    $httpMethod = strtoupper($matches[0][0]);
                    $methodUrl = substr($methodUrl, strlen($httpMethod));
                } else {
                    $httpMethod = 'GET';
                }
                if ($methodUrl == 'index') {
                    $methodUrl = '';
                }
                $url = empty($methodUrl) ? rtrim($resourcePath, '/')
                    : $resourcePath . $methodUrl;
                for ($position = 0; $position < count($params); $position++) {
                    $from = $metadata['param'][$position][$dataName]['from'];
                    if ($from == 'body' && ($httpMethod == 'GET' ||
                            $httpMethod == 'DELETE')
                    ) {
                        $call['metadata']['param'][$position][$dataName]['from']
                            = 'query';
                    }
                }
                if (empty($pathParams) || $allowAmbiguity) {
                    static::addPath($url, $call, $httpMethod, $version);
                }
                $lastPathParam = end($pathParams);
                foreach ($pathParams as $position) {
                    if (!empty($url)) {
                        $url .= '/';
                    }
                    $url .= '{' .
                        static::typeChar(isset($call['metadata']['param'][$position]['type'])
                            ? $call['metadata']['param'][$position]['type']
                            : null)
                        . $position . '}';
                    if ($allowAmbiguity || $position == $lastPathParam) {
                        static::addPath($url, $call, $httpMethod, $version);
                    }
                }
            }
        }
    }

    /**
     * protected methods will need at least one authentication class to be set
     * in order to allow that method to be executed
     *
     * @param string $className of the authentication class
     * @param string $resourcePath optional url for mapping
     *
     * @throws Exception
     */
    public static function addAuthenticator(string $className, string $resourcePath = null)
    {
        $implements = class_implements($className);
        if (!isset($implements[AuthenticationInterface::class])) {
            throw new Exception($className .
                ' is an invalid authenticator class; it must implement ' .
                'AuthenticationInterface.');
        }
        if (!in_array($className, App::$implementations[AuthenticationInterface::class])) {
            App::$implementations[AuthenticationInterface::class][] = $className;
        }
        if (isset($implements[AccessControlInterface::class]) &&
            !in_array($className, App::$implementations[AccessControlInterface::class])) {
            App::$implementations[AccessControlInterface::class][] = $className;
        }
        static::$authClasses[] = $className;
        static::mapApiClasses([$className => $resourcePath]);
    }

    /**
     * Classes implementing FilterInterface can be added for filtering out
     * the api consumers.
     *
     * It can be used for rate limiting based on usage from a specific ip
     * address or filter by country, device etc.
     *
     * @param string[] ...$classNames
     * @throws Exception
     */
    public static function setFilters(string ...$classNames)
    {
        static::$postAuthFilterClasses = [];
        static::$preAuthFilterClasses = [];
        foreach ($classNames as $className) {
            if (!isset(class_implements($className)[FilterInterface::class])) {
                throw new Exception($className . ' is an invalid filter class; it must implement ' .
                    'FilterInterface.');
            }
            if (isset(class_implements($className)[UsesAuthenticationInterface::class])) {
                static::$postAuthFilterClasses[] = $className;
            } else {
                static::$preAuthFilterClasses[] = $className;
            }
        }
    }

    /**
     * @param string $path
     * @param string $httpMethod
     * @param int $version
     * @param array $data
     * @return \Luracast\Restler\Data\ApiMethodInfo
     * @throws HttpException
     */
    public static function find(
        $path,
        $httpMethod,
        $version = 1,
        array $data = []
    ) {
        $p = Util::nestedValue(static::$routes, "v$version");
        if (!$p) {
            throw new HttpException(
                404,
                $version == 1 ? '' : "Version $version is not supported"
            );
        }
        $status = 404;
        $message = null;
        $methods = array();
        if (isset($p[$path][$httpMethod])) {
            //================== static routes ==========================
            return static::populate($p[$path][$httpMethod], $data);
        } elseif (isset($p['*'])) {
            //================== wildcard routes ========================
            uksort($p['*'], function ($a, $b) {
                return strlen($b) - strlen($a);
            });
            foreach ($p['*'] as $key => $value) {
                if (strpos($path, $key) === 0 && isset($value[$httpMethod])) {
                    //path found, convert rest of the path to parameters
                    $path = substr($path, strlen($key) + 1);
                    $call = ApiMethodInfo::__set_state($value[$httpMethod]);
                    $call->parameters = empty($path)
                        ? array()
                        : explode('/', $path);
                    return $call;
                }
            }
        }
        //================== dynamic routes =============================
        //add newline char if trailing slash is found
        if (substr($path, -1) == '/') {
            $path .= PHP_EOL;
        }
        //if double slash is found fill in newline char;
        $path = str_replace('//', '/' . PHP_EOL . '/', $path);
        ksort($p);
        foreach ($p as $key => $value) {
            if (!isset($value[$httpMethod])) {
                continue;
            }
            $regex = str_replace(array('{', '}'),
                array('(?P<', '>[^/]+)'), $key);
            if (preg_match_all(":^$regex$:i", $path, $matches, PREG_SET_ORDER)) {
                $matches = $matches[0];
                $found = true;
                foreach ($matches as $k => $v) {
                    if (is_numeric($k)) {
                        unset($matches[$k]);
                        continue;
                    }
                    $index = intval(substr($k, 1));
                    $details = $value[$httpMethod]['metadata']['param'][$index];
                    if ($k{0} == 's' || strpos($k, static::pathVarTypeOf($v)) === 0) {
                        //remove the newlines
                        $data[$details['name']] = trim($v, PHP_EOL);
                    } else {
                        $status = 400;
                        $message = 'invalid value specified for `'
                            . $details['name'] . '`';
                        $found = false;
                        break;
                    }
                }
                if ($found) {
                    return static::populate($value[$httpMethod], $data);
                }
            }
        }
        if ($status == 404) {
            //check if other methods are allowed
            if (isset($p[$path])) {
                $status = 405;
                $methods = array_keys($p[$path]);
            }
        }
        $e = new HttpException($status, $message);
        if ($status == 405) {
            $e->setHeader('Allow', implode(', ', $methods));
        }
        throw $e;
    }

    /**
     * @access private
     */
    public static function typeChar($type = null)
    {
        if (!$type) {
            return 's';
        }
        switch ($type{0}) {
            case 'i':
            case 'f':
                return 'n';
        }
        return 's';
    }

    protected static function addPath(
        $path,
        array $call,
        $httpMethod = 'GET',
        $version = 1
    ) {
        $call['url'] = preg_replace_callback(
            "/\{\S(\d+)\}/",
            function ($matches) use ($call) {
                return '{' .
                    $call['metadata']['param'][$matches[1]]['name'] . '}';
            },
            $path
        );
        //check for wildcard routes
        if (substr($path, -1, 1) == '*') {
            $path = rtrim($path, '/*');
            static::$routes["v$version"]['*'][$path][$httpMethod] = $call;
        } else {
            static::$routes["v$version"][$path][$httpMethod] = $call;
            //create an alias with index if the method name is index
            if ($call['methodName'] == 'index') {
                static::$routes["v$version"][ltrim("$path/index", '/')][$httpMethod] = $call;
            }
        }
    }

    /**
     * @param array $excludedPaths
     * @param array $excludedHttpMethods
     * @param int $version
     * @param bool $authenticated
     * @return array
     * @throws HttpException
     */
    public static function findAll(
        array $excludedPaths = array(),
        array $excludedHttpMethods = array(),
        $version = 1,
        $authenticated = false
    ) {
        $map = array();
        $all = Util::nestedValue(self::$routes, "v$version");
        $filter = array();
        if (isset($all['*'])) {
            $all = $all['*'] + $all;
            unset($all['*']);
        }
        if (is_array($all)) {
            foreach ($all as $fullPath => $routes) {
                foreach ($routes as $httpMethod => $route) {
                    if (in_array($httpMethod, $excludedHttpMethods)) {
                        continue;
                    }
                    foreach ($excludedPaths as $exclude) {
                        if (empty($exclude)) {
                            if ($fullPath == $exclude || $fullPath == 'index') {
                                continue 2;
                            }
                        } elseif (Text::beginsWith($fullPath, $exclude)) {
                            continue 2;
                        }
                    }
                    $hash = "$httpMethod " . $route['url'];
                    if (!isset($filter[$hash])) {
                        $route['httpMethod'] = $httpMethod;
                        $map[$route['metadata']['resourcePath']][] = [
                            'access' => static::verifyAccess($route, $authenticated),
                            'route' => $route,
                            'hash' => $hash
                        ];
                        $filter[$hash] = true;
                    }
                }
            }
        }
        return $map;
    }

    /**
     * @param array $route
     * @param $authenticated
     * @return bool
     * @throws HttpException
     */
    public static function verifyAccess(array $route, $authenticated)
    {
        if ($route['accessLevel'] < 2) {
            return true;
        }
        if (!$authenticated && $route['accessLevel'] > 1) {
            return false;
        }
        /** @var AccessControlInterface $class */
        if (
            $authenticated &&
            $class = App::getClass(AccessControlInterface::class) &&
                $class::__verifyAccess(ApiMethodInfo::__set_state($route))
        ) {
            return false;
        }
        return true;
    }


    /**
     * Populates the parameter values
     *
     * @param array $call
     * @param       $data
     *
     * @return ApiMethodInfo
     *
     * @access private
     */
    protected static function populate(array $call, $data)
    {
        $call['parameters'] = $call['defaults'];
        $p = &$call['parameters'];
        $dataName = CommentParser::$embeddedDataName;
        foreach ($data as $key => $value) {
            if (isset($call['arguments'][$key])) {
                $p[$call['arguments'][$key]] = $value;
            }
        }
        if (Defaults::$smartParameterParsing) {
            if (
                ($m = Util::nestedValue($call, 'metadata', 'param', 0)) &&
                !array_key_exists($m['name'], $data) &&
                array_key_exists(Defaults::$fullRequestDataName, $data) &&
                !is_null($d = $data[Defaults::$fullRequestDataName]) &&
                isset($m['type']) &&
                static::typeMatch($m['type'], $d)
            ) {
                $p[0] = $d;
            } else {
                $bodyParamCount = 0;
                $lastBodyParamIndex = -1;
                $lastM = null;
                foreach ($call['metadata']['param'] as $k => $m) {
                    if ($m[$dataName]['from'] == 'body') {
                        $bodyParamCount++;
                        $lastBodyParamIndex = $k;
                        $lastM = $m;
                    }
                }
                if (
                    $bodyParamCount == 1 &&
                    !array_key_exists($lastM['name'], $data) &&
                    array_key_exists(Defaults::$fullRequestDataName, $data) &&
                    !is_null($d = $data[Defaults::$fullRequestDataName])
                ) {
                    $p[$lastBodyParamIndex] = $d;
                }
            }
        }
        return ApiMethodInfo::__set_state($call);
    }

    /**
     * @access private
     * @param $var
     * @return string
     */
    protected static function pathVarTypeOf($var): string
    {
        if (is_numeric($var)) {
            return 'n';
        }
        if ($var === 'true' || $var === 'false') {
            return 'b';
        }
        return 's';
    }

    protected static function typeMatch(string $type, $var): bool
    {
        switch ($type) {
            case 'boolean':
            case 'bool':
                return is_bool($var);
            case 'array':
            case 'object':
                return is_array($var);
            case 'string':
            case 'int':
            case 'integer':
            case 'float':
            case 'number':
                return is_scalar($var);
        }
        return true;
    }

    protected static function parseMagic(ReflectionClass $class, $forResponse = true)
    {
        if (!$c = CommentParser::parse($class->getDocComment())) {
            return false;
        }
        $p = 'property';
        $r = empty($c[$p]) ? array() : $c[$p];
        $p .= '-' . ($forResponse ? 'read' : 'write');
        if (!empty($c[$p])) {
            $r = array_merge($r, $c[$p]);
        }

        return $r;
    }

    /**
     * Get the type and associated model
     *
     * @param ReflectionClass $class
     * @param array $scope
     *
     * @param string $prefix
     * @param array $rules
     * @return array
     *
     * @throws Exception
     * @throws RestException
     * @access protected
     */
    protected static function getTypeAndModel(
        ReflectionClass $class,
        array $scope,
        $prefix = '',
        array $rules = []
    ) {
        $className = $class->getName();
        $dataName = CommentParser::$embeddedDataName;
        if (isset(static::$models[$prefix . $className])) {
            return static::$models[$prefix . $className];
        }
        $children = array();
        try {
            if ($magic_properties = static::parseMagic($class, empty($prefix))) {
                foreach ($magic_properties as $prop) {
                    if (!isset($prop['name'])) {
                        throw new Exception('@property comment is not properly defined in ' . $className . ' class');
                    }
                    if (!isset($prop[$dataName]['label'])) {
                        $prop[$dataName]['label'] = Text::title($prop['name']);
                    }
                    if (isset(static::$fieldTypesByName[$prop['name']]) && $prop['type'] == 'string' && !isset($prop[$dataName]['type'])) {
                        $prop[$dataName]['type'] = static::$fieldTypesByName[$prop['name']];
                    }
                    $children[$prop['name']] = $prop;
                }
            } else {
                $props = $class->getProperties(ReflectionProperty::IS_PUBLIC);
                foreach ($props as $prop) {
                    $name = $prop->getName();
                    $child = array('name' => $name);
                    if ($c = $prop->getDocComment()) {
                        $child += Util::nestedValue(CommentParser::parse($c), 'var') ?: array();
                    } else {
                        $o = $class->newInstance();
                        $p = $prop->getValue($o);
                        if (is_object($p)) {
                            $child['type'] = get_class($p);
                        } elseif (is_array($p)) {
                            $child['type'] = 'array';
                            if (count($p)) {
                                $pc = reset($p);
                                if (is_object($pc)) {
                                    $child['contentType'] = get_class($pc);
                                }
                            }
                        }
                    }
                    $child += array(
                        'type' => isset(static::$fieldTypesByName[$child['name']])
                            ? static::$fieldTypesByName[$child['name']]
                            : 'string',
                        'label' => Text::title($child['name'])
                    );
                    isset($child[$dataName])
                        ? $child[$dataName] += array('required' => true)
                        : $child[$dataName]['required'] = true;
                    if ($prop->class != $className && $qualified = App::resolve($child['type'], $scope)) {
                        list($child['type'], $child['children'])
                            = static::getTypeAndModel(new ReflectionClass($qualified), $scope);
                    } elseif (
                        ($contentType = Util::nestedValue($child, $dataName, 'type')) &&
                        ($qualified = App::resolve($contentType, $scope))
                    ) {
                        list($child['contentType'], $child['children'])
                            = static::getTypeAndModel(new ReflectionClass($qualified), $scope);
                    }
                    $children[$name] = $child;
                }
            }
        } catch (Exception $e) {
            if (Text::endsWith($e->getFile(), 'CommentParser.php')) {
                throw new RestException(500, "Error while parsing comments of `$className` class. " . $e->getMessage());
            }
            throw $e;
        }
        if ($properties = Util::nestedValue($rules, 'properties')) {
            if (is_string($properties)) {
                $properties = array($properties);
            }
            $c = array();
            foreach ($properties as $property) {
                if (isset($children[$property])) {
                    $c[$property] = $children[$property];
                }
            }
            $children = $c;
        }
        if ($required = Util::nestedValue($rules, 'required')) {
            //override required on children
            if (is_bool($required)) {
                // true means all are required false means none are required
                $required = $required ? array_keys($children) : array();
            } elseif (is_string($required)) {
                $required = array($required);
            }
            $required = array_fill_keys($required, true);
            foreach ($children as $name => $child) {
                $children[$name][$dataName]['required'] = isset($required[$name]);
            }
        }
        static::$models[$prefix . $className] = array($className, $children, $prefix . $className);
        return static::$models[$prefix . $className];
    }

    /**
     * Import previously created routes from cache
     *
     * @param array $routes
     */
    public static function fromArray(array $routes)
    {
        static::$routes = $routes;
    }

    /**
     * Export current routes for cache
     *
     * @return array
     */
    public static function toArray()
    {
        return static::$routes;
    }

    public static function type($var)
    {
        if (is_object($var)) {
            return get_class($var);
        }
        if (is_array($var)) {
            return 'array';
        }
        if (is_bool($var)) {
            return 'boolean';
        }
        if (is_numeric($var)) {
            return is_float($var) ? 'float' : 'int';
        }
        return 'string';
    }

    public static function scope(ReflectionClass $class)
    {
        $namespace = $class->getNamespaceName();
        $imports = array(
            '*' => empty($namespace) ? '' : $namespace . '\\'
        );
        $file = file_get_contents($class->getFileName());
        $tokens = token_get_all($file);
        $namespace = '';
        $alias = '';
        $reading = false;
        $last = 0;
        foreach ($tokens as $token) {
            if (is_string($token)) {
                if ($reading && ',' == $token) {
                    //===== STOP =====//
                    $reading = false;
                    if (!empty($namespace)) {
                        $imports[$alias] = trim($namespace, '\\');
                    }
                    //===== START =====//
                    $reading = true;
                    $namespace = '';
                    $alias = '';
                } else {
                    //===== STOP =====//
                    $reading = false;
                    if (!empty($namespace)) {
                        $imports[$alias] = trim($namespace, '\\');
                    }
                }
            } elseif (T_USE == $token[0]) {
                //===== START =====//
                $reading = true;
                $namespace = '';
                $alias = '';
            } elseif ($reading) {
                //echo token_name($token[0]) . ' ' . $token[1] . PHP_EOL;
                switch ($token[0]) {
                    case T_WHITESPACE:
                        continue 2;
                    case T_STRING:
                        $alias = $token[1];
                        if (T_AS == $last) {
                            break;
                        }
                    //don't break;
                    case T_NS_SEPARATOR:
                        $namespace .= $token[1];
                        break;
                }
                $last = $token[0];
            }
        }
        return $imports;
    }
}