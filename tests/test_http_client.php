<?php declare(strict_types=1);


use LogicalSteps\Async\Async;

include __DIR__ . "/../api/bootstrap.php";

$loop = React\EventLoop\Factory::create();

ReactHttpClient::setLoop($loop);

Async::await(['ReactHttpClient::request', 'GET', 'http://localhost/', [], ''])->then(function ($result) {
    var_dump($result);
});

$loop->run();