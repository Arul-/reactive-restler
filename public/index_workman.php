<?php declare(strict_types=1);

use GuzzleHttp\Psr7\ServerRequest;
use function GuzzleHttp\Psr7\stream_for;
use Luracast\Restler\Reactler;
use Luracast\Restler\Utils\Dump;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

require __DIR__ . '/../api/bootstrap.php';

// #### http worker ####
$http_worker = new Worker("http://0.0.0.0:8080");

// 4 processes
$http_worker->count = 4;

// Emitted when data received
$http_worker->onMessage = function (TcpConnection $connection, $data) {
    $request = ServerRequest::fromGlobals();
    if (isset($GLOBALS['HTTP_RAW_REQUEST_DATA'])) {
        $request = $request->withBody(stream_for($GLOBALS['HTTP_RAW_REQUEST_DATA']));
    }
    //echo PHP_EOL . '-------------------------------------' . PHP_EOL;
    //echo Dump::request($request);
    $r = new Reactler();
    $response = $r->handle($request);
    $response_text = Dump::response($response);
    //echo $response_text;
    $connection->close($response_text, true);
};

// run all workers
Worker::runAll();