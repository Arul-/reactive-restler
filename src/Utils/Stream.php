<?php namespace Luracast\Restler\Utils;


use Luracast\Restler\Exceptions\Invalid;
use Psr\Http\Message\ServerRequestInterface;

class Stream
{
    const CRLF = "\r\n";

    /**
     * @param ServerRequestInterface $request
     * @param $outStream stream resource
     * @return null
     * @throws Invalid
     */
    public static function request(ServerRequestInterface $request, $outStream)
    {
        if (!is_resource($outStream) || get_resource_type($outStream) == 'stream') {
            throw new Invalid('expecting a stream resource');
        }
        fwrite(
            $outStream,
            $request->getMethod() . ' ' . $request->getUri() .
            ' HTTP/' . $request->getProtocolVersion() . PHP_EOL
        );
        foreach ($request->getHeaders() as $k => $v) {
            fwrite($outStream, ucwords($k) . ': ' . implode(', ', $v) . PHP_EOL);
        }
        fwrite($outStream, static::CRLF);
        $text .= urldecode((string)$request->getBody()) . static::CRLF . static::CRLF;
        return $text;
    }
}