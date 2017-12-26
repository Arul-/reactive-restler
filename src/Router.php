<?php declare(strict_types=1);


use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\Routes;
use Luracast\Restler\Util;

class Router extends Routes
{
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
}