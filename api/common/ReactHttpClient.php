<?php


use React\EventLoop\LoopInterface;
use React\HttpClient\Client;
use React\HttpClient\Response;

class ReactHttpClient implements HttpClientInterface
{
    static protected $loop;

    public static function setLoop(LoopInterface $loop)
    {
        static::$loop = $loop;
    }

    public static function request(
        string $method,
        string $uri,
        array $headers = [],
        string $body = '',
        callable $callback = null
    ) {
        if (!static::$loop) {
            throw new Error('Please call ReactHttpClient::setLoop before calling ReactHttpClient::request');
        }
        $client = new Client(static::$loop);
        $headers['Content-Length'] = strlen($body);
        $req = $client->request($method, $uri, $headers, '1.1');
        $req->on('response', function (Response $response) use ($callback) {
            $body = '';
            $response->on('data', function (string $chunk) use (&$body) {
                $body .= $chunk;
            });
            $response->on('end', function () use (&$body, $callback) {
                $callback(null, $body);
            });
        });
        $req->end($body);
    }
}