<?php declare(strict_types=1);

use Luracast\Restler\Restler;
use Luracast\Restler\Scope;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Http\Server;
use React\Promise\Promise;

include __DIR__ . "/../vendor/autoload.php";

$r = new Restle();


//examples
$r->addAPIClass('Say', 'examples/_001_helloworld/say');
$r->addAPIClass('Math', 'examples/_002_minimal/math');
$r->addAPIClass('BMI', 'examples/_003_multiformat/bmi');
$r->addAPIClass('Currency', 'examples/_004_error_response/currency');

$r->setSupportedFormats('JsonFormat', 'XmlFormat');

$r->addAPIClass('Simple', 'examples/_005_protected_api');
$r->addAPIClass('Secured', 'examples/_005_protected_api/secured');

$r->addAuthenticationClass('SimpleAuth', 'examples/_005_protected_api/SimpleAuth');

$r->addAPIClass('Api', 'examples/_006_routing/api');
$r->addAPIClass('Authors', 'examples/_007_crud/authors');

//tests
$r->addAPIClass('MinMax', 'tests/param/minmax');
$r->addAPIClass('MinMaxFix', 'tests/param/minmaxfix');
$r->addAPIClass('Type', 'tests/param/type');
$r->addAPIClass('Validation', 'tests/param/validation');
$r->addAPIClass('Data', 'tests/request_data');

$loop = React\EventLoop\Factory::create();

$server = new Server(function (ServerRequestInterface $request) use ($r) {
    return new Promise(function ($resolve, $reject) use ($request, $r) {
        echo '      ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . PHP_EOL;
        $content = "";
        $request->getBody()->on('data', function ($data) use (&$content) {
            $content .= $data;
        });

        $request->getBody()->on('end', function () use ($r, $request, $resolve, &$content) {
            $h = new Restle($r);
            Scope::set('Restler', $h);
            $resolve($h->handle($request, new Response(), $content));
        });

        // an error occurs e.g. on invalid chucked encoded data or an unexpected 'end' event
        $request->getBody()->on('error', function (\Exception $exception) use ($resolve, &$contentLength) {
            $response = new Response(
                400,
                ['Content-Type' => 'text/plain'],
                "An error occurred while reading at length: " . $contentLength
            );
            $resolve($response);
        });
    });
});

$socket = new React\Socket\Server(8080, $loop);
$server->listen($socket);

echo "Server running at http://127.0.0.1:8080\n";

$loop->run();