<?php namespace Luracast\Restler\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Dump
{
    const CRLF = "\r\n";

    public static function request(ServerRequestInterface $request): string
    {
        $text = static::requestHeaders($request);
        $text .= static::CRLF;
        $text .= urldecode((string)$request->getBody()) . static::CRLF . static::CRLF;
        return $text;
    }

    public static function requestHeaders(ServerRequestInterface $request): string
    {
        $text = $request->getMethod() . ' ' . $request->getUri() . ' HTTP/' . $request->getProtocolVersion() . PHP_EOL;
        foreach ($request->getHeaders() as $k => $v) {
            $text .= ucwords($k) . ': ' . implode(', ', $v) . PHP_EOL;
        }
        return $text;
    }

    public static function response(ResponseInterface $response, bool $headerAsString = true): string
    {
        $http = sprintf('HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );
        $text = '';
        if ($headerAsString) {
            $text .= $http . PHP_EOL;
            foreach ($response->getHeaders() as $k => $v) {
                $text .= ucwords($k) . ': ' . implode(', ', $v) . PHP_EOL;
            }
            $text .= static::CRLF;
        } else {
            header($http, true, $response->getStatusCode());
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header("$name: $value", false);
                }
            }
        }
        $text .= (string)$response->getBody();
        if ($headerAsString) {
            $text .= static::CRLF . static::CRLF;
        }
        return $text;
    }

    public static function backtrace(int $limit = 0): string
    {
        if ($limit) {
            $limit += 1;
        }
        $data = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $limit);
        $array = [];
        $stringer = function ($o) use (&$stringer) {
            if (is_string($o)) {
                return "'$o'";
            }
            if (is_scalar($o)) {
                return (string)$o;
            }
            if (is_object($o)) {
                return get_class($o);
            }
            if (is_array($o)) {
                return '[]';//' . implode(', ', array_map($stringer, $o)) . '
            }
        };
        foreach ($data as $index => $trace) {
            $array[$index] = array_pop(explode('/', $trace['file']))
                . ':' . $trace['line'] . ' ' . array_pop(explode('\\', $trace['class']))
                . '::' . $trace['function'] . '(' . implode(', ', array_map($stringer, $trace['args'])) . ')';
        }
        array_shift($array);
        return json_encode($array) . PHP_EOL;
    }
}