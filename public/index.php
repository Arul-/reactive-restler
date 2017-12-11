<?php

use Luracast\Restler\Restler;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Http\Server;
use React\Promise\Promise;

include __DIR__ . "/../vendor/autoload.php";

$r = new Restler();

$r->addAPIClass( 'MinMax', 'tests/param/minmax' );
$r->addAPIClass( 'MinMaxFix', 'tests/param/minmaxfix' );
$r->addAPIClass( 'Type', 'tests/param/type' );
$r->addAPIClass( 'Validation', '/tests/param/validation' );

$loop = React\EventLoop\Factory::create();

$server = new Server( function ( ServerRequestInterface $request ) {
	return new Promise( function ( $resolve, $reject ) use ( $request ) {
		echo '      ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . PHP_EOL;
		$content = "";
		$request->getBody()->on( 'data', function ( $data ) use ( &$content ) {
			$content .= $data;
		} );

		$request->getBody()->on( 'end', function () use ( $request, $resolve, &$content ) {
			$h = new Restle( $request, new Response(), $content );
			$resolve( $h->handle() );
		} );

		// an error occures e.g. on invalid chunked encoded data or an unexpected 'end' event
		$request->getBody()->on( 'error', function ( \Exception $exception ) use ( $resolve, &$contentLength ) {
			$response = new Response(
				400,
				[ 'Content-Type' => 'text/plain' ],
				"An error occured while reading at length: " . $contentLength
			);
			$resolve( $response );
		} );
	} );
} );

$socket = new React\Socket\Server( 8080, $loop );
$server->listen( $socket );

echo "Server running at http://127.0.0.1:8080\n";

$loop->run();