<?php namespace Luracast\Restler\MediaTypes;


use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\HttpException;
use Luracast\Restler\Utils\Convert;

class Json extends MediaType implements RequestMediaTypeInterface, ResponseMediaTypeInterface
{
    const MIME = 'application/json';
    const EXTENSION = 'json';

    public static $encodeOptions = 0;
    public static $decodeOptions = JSON_BIGINT_AS_STRING;

    public function decode(string $data)
    {
        $decoded = json_decode($data, static::$decodeOptions);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new HttpException(400, 'JSON Parser: ' . json_last_error_msg());
        }

        return Convert::toArray($decoded);
    }

    public function encode($data, bool $humanReadable = false): string
    {
        $options = static::$encodeOptions;
        if ($humanReadable) {
            $options |= JSON_PRETTY_PRINT;
        }

        return json_encode(Convert::toArray($data, true), $options);
    }
}