<?php


namespace Workerman\Protocols;


use Luracast\Restler\Reactler;
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
     * @throws \Luracast\Restler\Exceptions\HttpException
     */
    public static function decode($recv_buffer, TcpConnection $connection): void
    {
        $request = parent::decode($recv_buffer, $connection);
        (new Reactler())->handle($request)->then(function (ResponseInterface $response) use ($connection) {
            $connection->close(Dump::response($response), true);
        });
    }
}