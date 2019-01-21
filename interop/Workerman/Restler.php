<?php


namespace Workerman\Protocols;


use Luracast\Restler\Restler as Server;
use Luracast\Restler\Utils\Dump;
use Luracast\Restler\Exceptions\HttpException;
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
    public static function decode($recv_buffer, TcpConnection $connection): void
    {
        $request = parent::decode($recv_buffer, $connection);
        (new Server())->handle($request)->then(function (ResponseInterface $response) use ($connection) {
            $connection->close(Dump::response($response), true);
        });
    }
}