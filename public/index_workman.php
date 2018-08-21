<?php declare(strict_types=1);

use GuzzleHttp\Psr7\ServerRequest;
use function GuzzleHttp\Psr7\stream_for;
use Luracast\Restler\Reactler;
use Luracast\Restler\Utils\Dump;
use Workerman\Worker;

require __DIR__ . '/../api/bootstrap.php';

// #### http worker ####
$http_worker = new Worker("http://0.0.0.0:8080");

// 4 processes
$http_worker->count = 4;

// Emitted when data received
$http_worker->onMessage = function ($connection, $data) {
    /*
    $headers = read_headers($data['server']);
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    $uri = ServerRequest::getUriFromGlobals();
    $request = new ServerRequest($method, $uri, $headers,
        $GLOBALS['HTTP_RAW_REQUEST_DATA'], '1.1', $data['server']);
    */
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
    $connection->send($response_text, true);
    $connection->close();
};

// run all workers
Worker::runAll();