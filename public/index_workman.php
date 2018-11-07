<?php declare(strict_types=1);

use Luracast\Restler\Reactler;
use Luracast\Restler\Utils\Dump;
use Psr\Http\Message\ResponseInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

require __DIR__ . '/../api/bootstrap.php';

// #### http worker ####
$http_worker = new Worker("http://0.0.0.0:8080");

// 4 processes
$http_worker->count = 4;

// Emitted when data received
$http_worker->onMessage = function (TcpConnection $connection, $data) {
    $r = new Reactler();
    $r->handle()->then(function (ResponseInterface $response) use ($connection) {
        $connection->close(Dump::response($response), true);
    });
};

// run all workers
Worker::runAll();