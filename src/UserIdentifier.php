<?php namespace Luracast\Restler;

use Luracast\Restler\Contracts\UserIdentificationInterface;

class UserIdentifier implements UserIdentificationInterface
{
    private static $initialized = false;
    public static $id = null;
    public static $cacheId = null;
    public static $ip;
    public static $browser = '';
    public static $platform = '';

    public static function init()
    {
        static::$initialized = true;
        static::$ip = static::getIpAddress();
    }

    public static function getUniqueIdentifier($includePlatform = false)
    {
        if (!static::$initialized) {
            static::init();
        }
        return static::$id ?: base64_encode('ip:' . ($includePlatform
                ? static::$ip . '-' . static::$platform
                : static::$ip
            ));
    }

    public static function getIpAddress($ignoreProxies = false)
    {
        foreach (array(
                     'HTTP_CLIENT_IP',
                     'HTTP_X_FORWARDED_FOR',
                     'HTTP_X_FORWARDED',
                     'HTTP_X_CLUSTER_CLIENT_IP',
                     'HTTP_FORWARDED_FOR',
                     'HTTP_FORWARDED',
                     'REMOTE_ADDR'
                 ) as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); // just to be safe

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4
                            | FILTER_FLAG_NO_PRIV_RANGE
                            | FILTER_FLAG_NO_RES_RANGE) !== false
                    ) {
                        return $ip;
                    }
                }
            }
        }
    }

    /**
     * Authentication classes should call this method
     *
     * @param string $id user id as identified by the authentication classes
     *
     * @return void
     */
    public static function setUniqueIdentifier($id)
    {
        static::$id = $id;
    }

    /**
     * User identity to be used for caching purpose
     *
     * When the dynamic cache service places an object in the cache, it needs to
     * label it with a unique identifying string known as a cache ID. This
     * method gives that identifier
     *
     * @return string
     */
    public static function getCacheIdentifier()
    {
        return static::$cacheId ?: static::$id;
    }

    /**
     * User identity for caching purpose
     *
     * In a role based access control system this will be based on role
     *
     * @param $id
     *
     * @return void
     */
    public static function setCacheIdentifier($id)
    {
        static::$cacheId = $id;
    }
}