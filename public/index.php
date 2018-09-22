<?php declare(strict_types=1);

use GuzzleHttp\Psr7\ServerRequest;
use function GuzzleHttp\Psr7\stream_for;
use Luracast\Restler\Reactler;
use Luracast\Restler\Utils\Dump;

require __DIR__ . '/../api/bootstrap.php';

$request = ServerRequest::fromGlobals();
if (isset($GLOBALS['HTTP_RAW_REQUEST_DATA'])) {
    $request = $request->withBody(stream_for($GLOBALS['HTTP_RAW_REQUEST_DATA']));
}
$r = new Reactler();
$response = $r->handle($request);
echo Dump::response($response, false);