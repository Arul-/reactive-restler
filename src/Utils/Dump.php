<?php namespace Luracast\Restler\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Dump
{
    const CRLF = "\r\n";

    public static function request(ServerRequestInterface $request): string
    {
        $text = $request->getMethod() . ' ' . $request->getUri() . ' HTTP/' . $request->getProtocolVersion() . PHP_EOL;
        foreach ($request->getHeaders() as $k => $v) {
            $text .= ucwords($k) . ': ' . implode(', ', $v) . PHP_EOL;
        }
        $text .= static::CRLF;
        $text .= urldecode((string)$request->getBody()) . static::CRLF . static::CRLF;
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
}