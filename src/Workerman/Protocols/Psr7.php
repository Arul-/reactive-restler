<?php

namespace Workerman\Protocols;


use function GuzzleHttp\Psr7\parse_query;
use Luracast\Restler\Utils\ClassName;
use Psr\Http\Message\ServerRequestInterface;
use Workerman\Connection\TcpConnection;

class Psr7 extends Http
{
    /**
     * Parse $_POST、$_GET、$_COOKIE.
     *
     * @param string $recv_buffer
     * @param TcpConnection $connection
     * @return ServerRequestInterface
     * @throws \Luracast\Restler\Exceptions\HttpException
     */
    public static function decode($recv_buffer, TcpConnection $connection)
    {
        list($http_header, $http_body) = explode("\r\n\r\n", $recv_buffer, 2);
        $header_strings = explode("\r\n", $http_header);
        list($method, $uri, $protocol) = explode(' ', $header_strings[0]);
        array_shift($header_strings);
        $headers = [];
        foreach ($header_strings as $header_string) {
            list ($key, $value) = explode(': ', $header_string);
            $headers[$key] = $value;
        }
        $uri = ($connection->transport == 'ssl' ? 'https://' : 'http://') . $headers['Host'] . $uri;
        $server = [
            'REMOTE_ADDR' => $connection->getRemoteIp(),
            'REMOTE_PORT' => $connection->getRemotePort()
        ];
        $class = ClassName::get(ServerRequestInterface::class);
        /** @var ServerRequestInterface $request */
        $request = new $class($method, $uri, $headers, $http_body, $protocol, $server);
        $request = $request->withQueryParams(parse_query(parse_url($uri, PHP_URL_QUERY)));
        return $request;
    }
}