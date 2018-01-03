<?php namespace Luracast\Restler\Contracts;

use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\RestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;


interface BaseInterface
{
    function get(): void;

    function getPath(string $path): string;

    function getQuery(array $get = []): array;

    function getRequestMediaType(string $contentType): RequestMediaTypeInterface;

    function getBody(string $raw = ''): array;


    function route(): void;


    function negotiate(): void;

    function negotiateResponseMediaType(string $path, string $acceptHeader = ''): ResponseMediaTypeInterface;

    function negotiateCORS(string $requestMethod, string $accessControlRequestMethod = '', string $accessControlRequestHeaders = '', string $origin = ''): void;

    function negotiateCharset(string $acceptCharset = '*'): void;

    function negotiateLanguage(string $acceptLanguage = ''): void;


    function authenticate();


    function validate();


    function call();


    function compose($response = null);

    function composeHeaders(?ApiMethodInfo $info, string $origin = '', RestException $e = null): void;

    function message(Throwable $e);


    function respond($response = []): ResponseInterface;

    function stream($data): ResponseInterface;


    function handle(ServerRequestInterface $request, ResponseInterface $response, string $rawRequestBody = ''): ResponseInterface;
}