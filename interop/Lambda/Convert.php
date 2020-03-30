<?php /** @noinspection PhpInternalEntityUsedInspection */


namespace Lambda;


use Bref\Context\Context;
use Bref\Event\Http\HttpRequestEvent;
use Bref\Event\Http\HttpResponse;
use Bref\Event\Http\Psr7Bridge;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Convert
{
    public final static function toPSR7(array $payload, array $headers): ServerRequestInterface
    {
        $httpEvent = new HttpRequestEvent($payload);
        $context = new Context(
            $headers['lambda-runtime-aws-request-id'],
            (int)$headers['lambda-runtime-deadline-ms'],
            $headers['lambda-runtime-invoked-function-arn'],
            $headers['lambda-runtime-trace-id']
        );
        return Psr7Bridge::convertRequest($httpEvent, $context);
    }

    public final static function fromPSR7(ResponseInterface $psr7Response): array
    {
        $response = new HttpResponse(
            (string)$psr7Response->getBody(),
            $psr7Response->getHeaders(),
            $psr7Response->getStatusCode()
        );
        return $response->toApiGatewayFormat();
    }

}
