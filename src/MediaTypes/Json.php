<?php namespace Luracast\Restler\MediaTypes;


use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Utils\Convert;

class Json extends MediaType implements RequestMediaTypeInterface, ResponseMediaTypeInterface
{
    const MIME = 'application/json';
    const EXTENSION = 'json';

    public static $encodeOptions = JSON_UNESCAPED_SLASHES;
    public static $decodeOptions = JSON_BIGINT_AS_STRING;

    /**
     * @param string $data
     * @return array|mixed
     * @throws HttpException
     */
    public function decode(string $data)
    {
        if (!strlen($data)) {
            return [];
        }
        $decoded = json_decode($data, true, 512, static::$decodeOptions);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new HttpException(400, 'JSON Parser: ' . json_last_error_msg());
        }
        if (strlen($data) && $decoded === null || $decoded === $data) {
            throw new HttpException(400, 'Error parsing JSON');
        }
        return $decoded; //Convert::toArray($decoded);
    }

    /**
     * @param $data
     * @param bool $humanReadable
     * @return string
     * @throws HttpException
     */
    public function encode($data, bool $humanReadable = false): string
    {
        $options = static::$encodeOptions;
        if ($humanReadable) {
            $options |= JSON_PRETTY_PRINT;
        }
        $encoded = json_encode(Convert::toArray($data, true), $options);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new HttpException(500, 'JSON Parser: ' . json_last_error_msg());
        }
        return $encoded;
    }
}