<?php

namespace Workerman\Protocols;


use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Utils\ClassName;
use Psr\Http\Message\ServerRequestInterface;
use Workerman\Connection\TcpConnection;

class Psr7 extends Http
{
    /**
     * Check the integrity of the package.
     *
     * @param string $recv_buffer
     * @param TcpConnection $connection
     * @return int
     */
    public static function input($recv_buffer, TcpConnection $connection)
    {
        static $seperator = "\r\n\r\n";
        if (!strpos($recv_buffer, $seperator)) {
            // Judge whether the package length exceeds the limit.
            if (strlen($recv_buffer) >= $connection->maxPackageSize) {
                $connection->close();
            }
            return 0;
        }

        list($header, $body) = explode($seperator, $recv_buffer, 2);
        $method = substr($header, 0, strpos($header, ' '));

        if (in_array($method, static::$methods)) {
            return strlen($header);
        } else {
            $connection->send("HTTP/1.1 400 Bad Request$seperator", true);
            return 0;
        }
    }

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
        $http_header = $recv_buffer;
        $header_strings = explode("\r\n", $http_header);
        list($method, $uri, $protocol) = explode(' ', $header_strings[0]);
        echo "$method $uri\n";
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
        $stream = $connection->getSocket();
        //$connection->resumeRecv();
        $length = (int)$headers['Content-Length'] ?? 10;
        //$body = fread($stream, $length);
        $body = '';
        while (!feof($stream)) {
            $body .= fread($stream, 8192);
        }
        $class = ClassName::get(ServerRequestInterface::class);
        /** @var ServerRequestInterface $request */
        $request = new $class($method, $uri, $headers, $body, $protocol, $server);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query);
        return $request;
    }
}