<?php namespace Luracast\Restler;


use Luracast\Restler\Contracts\ComposerInterface;
use Luracast\Restler\Exceptions\HttpException;

class Composer implements ComposerInterface
{
    /**
     * @var bool When restler is not running in production mode, this value will
     * be checked to include the debug information on error response
     */
    public static $includeDebugInfo = true;

    /**
     * Result of an api call is passed to this method
     * to create a standard structure for the data
     *
     * @param mixed $result can be a primitive or array or object
     *
     * @return mixed
     */
    public function response($result)
    {
        return $result;
    }

    /**
     * When the api call results in HttpException this method
     * will be called to return the error message
     *
     * @param HttpException $exception exception that has reasons for failure
     *
     * @return mixed
     */
    public function message(HttpException $exception)
    {
        $r = [
            'error' => [
                    'code' => $exception->getCode(),
                    'message' => $exception->getErrorMessage(),
                ] + $exception->getDetails()
        ];
        if (!Defaults::$productionMode && self::$includeDebugInfo) {
            $innerException = $exception;
            while ($prev = $innerException->getPrevious()) {
                $innerException = $prev;
            }
            $trace = array_slice($innerException->getTrace(), 0, 10);
            $r['debug'] = [
                'source' => $exception->getSource(),
                'trace' => array_map([static::class, 'simplifyTrace'], $trace)
            ];
        }
        return $r;
    }

    public static function simplifyTrace(array $trace)
    {
        $parts = explode('\\', $trace['class'] ?? '');
        $class = array_pop($parts);
        $parts = explode('/', $trace['file'] ?? '');
        return [
            'file' => array_pop($parts) . (isset($trace['line']) ? ':' . $trace['line'] : ''),
            'function' => $class . ($trace['type'] ?? '') . $trace['function'],
            'args' => array_map([static::class, 'simplifyTraceArgs'], $trace['args']),
        ];
    }

    public static function simplifyTraceArgs($argument)
    {
        if (is_object($argument)) {
            return 'new ' . get_class($argument) . '()';
        }
        if (is_array($argument)) {
            return array_map(__METHOD__, $argument);
        }
        if (is_resource($argument)) {
            return 'resource(' . get_resource_type($argument) . ')';
        }
        return $argument;
    }
}