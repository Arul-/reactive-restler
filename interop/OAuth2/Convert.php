<?php

namespace OAuth2;


use Luracast\Restler\Utils\ClassName;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\ResponseInterface as PSRResponse;

class Convert
{
    public final static function fromPSR7(ServerRequestInterface $psrRequest): Request
    {
        $contents = $psrRequest->getBody()->getContents();
        $psrRequest->getBody()->rewind();
        return new Request(
            (array)$psrRequest->getQueryParams(),
            (array)$psrRequest->getParsedBody(),
            $psrRequest->getAttributes(),
            $psrRequest->getCookieParams(),
            self::convertUploadedFiles($psrRequest->getUploadedFiles()),
            $psrRequest->getServerParams(),
            $contents,
            self::cleanupHeaders($psrRequest->getHeaders())
        );
    }

    public final static function toPSR7(Response $oauthrResponse): PSRResponse
    {
        $headers = [];
        foreach ($oauthrResponse->getHttpHeaders() as $key => $value) {
            $headers[$key] = explode(', ', $value);
        }
        $body = '';

        if (!empty($oauthrResponse->getParameters())) {
            $body = $oauthrResponse->getResponseBody();
        }
        $class = ClassName::get(PSRResponse::class);
        $response = new $class($oauthrResponse->getStatusCode(), $headers, (string)$body);

        return $response;
    }

    /**
     * Helper method to clean header keys and values.
     *
     * Slim will convert all headers to Camel-Case style. There are certain headers such as PHP_AUTH_USER that the
     * OAuth2 library requires CAPS_CASE format. This method will adjust those headers as needed.  The OAuth2 library
     * also does not expect arrays for header values, this method will implode the multiple values with a ', '
     *
     * @param array $uncleanHeaders The headers to be cleaned.
     *
     * @return array The cleaned headers
     */
    private static function cleanupHeaders(array $uncleanHeaders = [])
    {
        $cleanHeaders = [];
        $headerMap = [
            'Php-Auth-User' => 'PHP_AUTH_USER',
            'Php-Auth-Pw' => 'PHP_AUTH_PW',
            'Php-Auth-Digest' => 'PHP_AUTH_DIGEST',
            'Auth-Type' => 'AUTH_TYPE',
            'HTTP_AUTHORIZATION' => 'AUTHORIZATION',
        ];
        foreach ($uncleanHeaders as $key => $value) {
            if (array_key_exists($key, $headerMap)) {
                $key = $headerMap[$key];
            }
            $cleanHeaders[$key] = is_array($value) ? implode(', ', $value) : $value;
        }
        return $cleanHeaders;
    }

    /**
     * Convert a PSR-7 uploaded files structure to a $_FILES structure.
     *
     * @param array $uploadedFiles Array of file objects.
     *
     * @return array
     */
    private static function convertUploadedFiles(array $uploadedFiles)
    {
        $files = [];
        foreach ($uploadedFiles as $name => $uploadedFile) {
            if (!is_array($uploadedFile)) {
                $files[$name] = self::convertUploadedFile($uploadedFile);
                continue;
            }
            $files[$name] = [];
            foreach ($uploadedFile as $file) {
                $files[$name][] = self::convertUploadedFile($file);
            }
        }
        return $files;
    }

    private static function convertUploadedFile(UploadedFileInterface $uploadedFile)
    {
        return [
            'name' => $uploadedFile->getClientFilename(),
            'type' => $uploadedFile->getClientMediaType(),
            'size' => $uploadedFile->getSize(),
            'tmp_name' => $uploadedFile->getStream()->getMetadata('uri'),
            'error' => $uploadedFile->getError(),
        ];
    }

}