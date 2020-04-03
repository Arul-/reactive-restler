<?php

use Swoole\Coroutine\Http\Client;

class SwooleHttpClient implements HttpClientInterface
{
    public static function request(
        string $method,
        string $uri,
        array $headers = [],
        string $body = '',
        callable $callback = null
    )
    {
        go(function () use ($method, $uri, $headers, $body, $callback) {
            $parts = parse_url($uri);
            $client = new Client($parts['host'], $parts['port']);
            $client->setMethod($method);
            $client->setHeaders($headers);
            if (!empty($body)) {
                $client->setData($body);
            }
            $client->execute($uri);
            $callback(null, $client->body);
        });
    }
}
