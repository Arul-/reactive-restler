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

    public static function response(ResponseInterface $response): string
    {
        $text = 'HTTP/' . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' '
            . $response->getReasonPhrase() . PHP_EOL;
        foreach ($response->getHeaders() as $k => $v) {
            $text .= ucwords($k) . ': ' . implode(', ', $v) . PHP_EOL;
        }
        $text .= static::CRLF;
        $text .= (string)$response->getBody() . static::CRLF . static::CRLF;
        return $text;
    }
}