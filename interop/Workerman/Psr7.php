<?php

namespace Workerman\Protocols;

use RingCentral\Psr7\AppendStream;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Utils\ClassName;
use Psr\Http\Message\ServerRequestInterface;
use function RingCentral\Psr7\stream_for;
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
            $connection->body = stream_for($body);
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
        $length = (int)($headers['Content-Length'] ?? 10);
        //$body = fread($stream, $length);
        //$connection->consumeRecvBuffer($length);
        //$body = '';
        /*
        $pos = ftell($stream);
        $new_pos = fseek($fp, $pos - 32);
        while (!feof($stream)) {
            $body .= fread($stream, 8192);
        }
        */
        $body = $connection->body;//new AppendStream([$connection->body, stream_for($connection->getSocket())]);
        //$body = $body->getContents();
        $class = ClassName::get(ServerRequestInterface::class);
        /** @var ServerRequestInterface $request */
        $request = new $class($method, $uri, $headers, $body, $protocol, $server);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query);
        $connection->resumeRecv();
        return $request;
    }
}