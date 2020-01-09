<?php namespace Luracast\Restler\Contracts;

use Psr\Http\Message\StreamInterface;

interface ResponseMediaTypeInterface extends MediaTypeInterface
{
    /**
     * Encode the response into specific media type
     * @param array|object $data
     * @param array $responseHeaders
     * @param bool $humanReadable
     * @return string|resource|StreamInterface
     */
    public function encode($data, array &$responseHeaders, bool $humanReadable = false);
}
