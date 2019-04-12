<?php

class SwooleHttpClient implements HttpClientInterface
{

    public static function request(
        string $method,
        string $uri,
        array $headers = [],
        string $body = '',
        callable $callback = null
    ) {
        $parts = parse_url($uri);
        $client = new swoole_http_client($parts['host'], $parts['port']);
        $client->setMethod($method);
        $client->setHeaders($headers);
        if (!empty($body)) {
            $client->setData($body);
        }
        $client->execute($uri, function ($cli) use ($callback) {
            $callback(null, $cli->body);
        });
    }
}