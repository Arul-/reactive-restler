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


function removeCommonPath($fromPath, $usingPath, $char = '/')
{
    if (empty($fromPath)) {
        return '';
    }
    $fromPath = explode($char, $fromPath);
    $usingPath = explode($char, $usingPath);
    while (count($usingPath)) {
        if (count($fromPath) && $fromPath[0] == $usingPath[0]) {
            array_shift($fromPath);
        } else {
            break;
        }
        array_shift($usingPath);
    }
    return implode($char, $fromPath);
}

$fullPath = urldecode($_SERVER['REQUEST_URI']);
$uri = $request->getUri();
$path = removeCommonPath(
    $fullPath,
    $_SERVER['SCRIPT_NAME']
);
$path = rtrim(strtok($path, '?'), '/');
$debug = false;
if ($debug) {
    echo var_export($_SERVER);
    echo PHP_EOL . PHP_EOL;
    echo var_export($request->getServerParams());
    echo PHP_EOL . PHP_EOL;
    echo Dump::request($request);
    echo "Path: $path\n\n";
}

$uri = $uri->withPath($path);
$request = $request->withUri($uri);

$r = new Reactler();
$response = $r->handle($request);
echo Dump::response($response, $debug);