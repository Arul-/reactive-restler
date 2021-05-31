<?php

namespace Workerman\Protocols;

use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Restler as Server;
use Luracast\Restler\Utils\Dump;
use Psr\Http\Message\ResponseInterface;
use Workerman\Connection\TcpConnection;

class Restler extends Psr7
{
    /**
     * Parse $_POST、$_GET、$_COOKIE.
     *
     * @param string $recv_buffer
     * @param TcpConnection $connection
     * @return void
     * @throws HttpException
     */
    public static function decode($recv_buffer, TcpConnection $connection)
    {
        $request = parent::decode($recv_buffer, $connection);
        (new Server())->handle($request)->then(
            function (ResponseInterface $response) use ($connection) {
                $data = Dump::response($response, false);
                $data_size = strlen($data);
                //send headers alone first
                $connection->send(
                    Dump::responseHeaders($response->withHeader('Content-Length', $data_size), true),
                    true
                );
                //send body content
                $connection->send($data, true);
            }
        );
    }
}
