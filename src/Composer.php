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
            $r += [
                'debug' => [
                    'source' => $exception->getSource(),
                    'trace' => $innerException->getTrace()
                ],
            ];
        }
        return $r;
    }
}