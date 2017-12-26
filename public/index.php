<?php declare(strict_types=1);

use Luracast\Restler\Scope;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Http\Server;
use React\Promise\Promise;

include __DIR__ . "/../vendor/autoload.php";

//examples
Router::addAPI('Say', 'examples/_001_helloworld/say');
Router::addAPI('Math', 'examples/_002_minimal/math');
Router::addAPI('BMI', 'examples/_003_multiformat/bmi');
Router::addAPI('Currency', 'examples/_004_error_response/currency');

Router::setSupportedFormats('JsonFormat', 'XmlFormat');

Router::addAPI('Simple', 'examples/_005_protected_api');
Router::addAPI('Secured', 'examples/_005_protected_api/secured');

Router::addAuthenticator('SimpleAuth', 'examples/_005_protected_api/SimpleAuth');

Router::addAPI('Api', 'examples/_006_routing/api');
Router::addAPI('Authors', 'examples/_007_crud/authors');

//tests
Router::addAPI('MinMax', 'tests/param/minmax');
Router::addAPI('MinMaxFix', 'tests/param/minmaxfix');
Router::addAPI('Type', 'tests/param/type');
Router::addAPI('Validation', 'tests/param/validation');
Router::addAPI('Data', 'tests/request_data');

$loop = React\EventLoop\Factory::create();

$server = new Server(function (ServerRequestInterface $request) {
    return new Promise(function ($resolve, $reject) use ($request) {
        echo '      ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . PHP_EOL;
        $content = "";
        $request->getBody()->on('data', function ($data) use (&$content) {
            $content .= $data;
        });

        $request->getBody()->on('end', function () use ($request, $resolve, &$content) {
            $h = new Restle();
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