<?php

namespace Workerman\Protocols;


use Luracast\Restler\Exceptions\HttpException;
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
     * @throws HttpException
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
        if ('POST' == $method && isset($headers['Content-Type'])) {
            if (preg_match('/boundary="?(\S+)"?/', $headers['Content-Type'], $match)) {
                $headers['Content-Type'] = 'multipart/form-data';
                $http_post_boundary = '--' . $match[1];
                static::parseUploadFiles($http_body, $http_post_boundary);
                $request = $request->withUploadedFiles($_FILES);
            }
        }
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query);
        $cookieHeader = $request->getHeaderLine('Cookie');
        if (empty($cookieHeader)) {
            return $request;
        }
        $cookies = array();
        parse_str(str_replace('; ', '&', $cookieHeader), $cookies);
        return $request->withCookieParams($cookies);

    }
}