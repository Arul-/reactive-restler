<?php namespace Luracast\Restler\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Dump
{
    public static function request(ServerRequestInterface $request): string
    {
        $text = $request->getMethod() . ' ' . $request->getUri() . ' HTTP/' . $request->getProtocolVersion() . PHP_EOL;
        foreach ($request->getHeaders() as $k => $v) {
            $text .= ucwords($k) . ': ' . implode(', ', $v) . PHP_EOL;
        }
        $text .= PHP_EOL;
        $text .= urldecode((string)$request->getBody()) . PHP_EOL;
        return $text;
    }

    public static function response(ResponseInterface $response): string
    {
        $text = 'HTTP/' . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' '
            . $response->getReasonPhrase() . PHP_EOL;
        foreach ($response->getHeaders() as $k => $v) {
            $text .= ucwords($k) . ': ' . implode(', ', $v) . PHP_EOL;
        }
        $text .= PHP_EOL;
        $text .= (string)$response->getBody();
        return $text;
    }
}