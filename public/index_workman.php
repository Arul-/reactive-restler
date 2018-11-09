<?php declare(strict_types=1);

use Workerman\Worker;

require __DIR__ . '/../api/bootstrap.php';

$worker = new Worker('restler://0.0.0.0:8080');
$worker->count = 4;
// run all workers
Worker::runAll();