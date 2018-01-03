<?php namespace Luracast\Restler;


use Exception;
use Luracast\Restler\Contracts\MediaTypeInterface;
use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\Format\iFormat;
use Throwable;

class Router extends Routes
{
    public static $authClasses = [];
    public static $formatMap = ['extensions' => []];
    public static $versionMap = [];

    public static $minimumVersion = 1;
    public static $maximumVersion = 1;

    protected static $readableMediaTypes = [];
    protected static $writableMediaTypes = [];

    public static function setApiVersion(int $maximum = 1, int $minimum = 1)
    {
        static::$maximumVersion = $maximum;
        static::$minimumVersion = $minimum;
    }

    public static function setMediaTypes(string ...$types): void
    {
        $extensions = [];
        Router::$writableMediaTypes = Router::$readableMediaTypes = [];
        Router::$formatMap = [];
        foreach ($types as $type) {
            $implements = class_implements($type);
            if (!$implements[MediaTypeInterface::class]) {
                throw new Exception($type . 'is an invalid media type class; it must implement ' .
                    'MediaTypeInterface interface');
            }
            $either = false;
            foreach ($type::supportedMediaTypes() as $mime => $extension) {
                if ($implements[ResponseMediaTypeInterface::class]) {
                    $either = true;
                    Router::$writableMediaTypes[] = $mime;
                    $extensions[".$extension"] = true;
                    if (!isset(Router::$formatMap['default'])) {
                        Router::$formatMap['default'] = $type;
                    }
                    if (!isset(Router::$formatMap[$extension])) {
                        Router::$formatMap[$extension] = $type;
                    }
                    if (!isset(Router::$formatMap[$mime])) {
                        Router::$formatMap[$mime] = $type;
                    }
                }
                if ($implements[RequestMediaTypeInterface::class]) {
                    $either = true;
                    Router::$readableMediaTypes[] = $mime;
                    if (!isset(Router::$formatMap[$mime])) {
                        Router::$formatMap[$mime] = $type;
                    }
                }
            }
            if (!$either) {
                throw new Exception($type . 'is an invalid media type class; it must implement ' .
                    'either RequestMediaTypeInterface or ResponseMediaTypeInterface interface');
            }
        }
        Router::$formatMap['extensions'] = array_keys($extensions);
    }

    public static function setRequestMediaTypes(string ...$types): void
    {
        Router::$readableMediaTypes = [];
        foreach ($types as $type) {
            if (!class_implements($type)[RequestMediaTypeInterface::class]) {
                throw new Exception($type . 'is an invalid media type class; it must implement ' .
                    'RequestMediaTypeInterface interface');
            }
            foreach ($type::supportedMediaTypes() as $mime => $extension) {
                Router::$readableMediaTypes[] = $mime;
                if (!isset(Router::$formatMap[$mime])) {
                    Router::$formatMap[$mime] = $type;
                }
            }
        }
    }

    public static function setResponseMediaTypes(string ...$types): void
    {
        $extensions = [];
        Router::$writableMediaTypes = [];

        foreach ($types as $type) {
            if (!class_implements($type)[ResponseMediaTypeInterface::class]) {
                throw new Exception($type . 'is an invalid media type class; it must implement ' .
                    'ResponseMediaTypeInterface interface');
            }
            foreach ($type::supportedMediaTypes() as $mime => $extension) {
                Router::$writableMediaTypes[] = $mime;
                $extensions[".$extension"] = true;
                if (!isset(Router::$formatMap[$extension])) {
                    Router::$formatMap[$extension] = $type;
                }
                if (!isset(Router::$formatMap[$mime])) {
                    Router::$formatMap[$mime] = $type;
                }
            }
        }
        Router::$formatMap['default'] = $types[0];
        Router::$formatMap['extensions'] = array_keys($extensions);
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
     * @param array $map [$className => $resourcePath, $className2 ...]
     *                   array of associative arrays containing the
     *                   class name & optional url prefix for mapping.
     *
     * @throws Exception
     */
    public static function mapApiClasses(array $map): void
    {
        try {
            $maxVersionMethod = '__getMaximumSupportedVersion';
            foreach ($map as $className => $resourcePath) {
                if (is_numeric($className)) {
                    $className = $resourcePath;
                    $resourcePath = null;
                }
                if (isset(Scope::$classAliases[$className])) {
                    $className = Scope::$classAliases[$className];
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
                        Routes::addAPIClass($versionedClassName,
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
                        Routes::addAPIClass(static::$versionMap[$className][$version],
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
        static::mapApiClasses([$className => $resourcePath]);
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
        static::$authClasses[] = $className;
        static::mapApiClasses([$className => $resourcePath]);
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
    )
    {
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
}