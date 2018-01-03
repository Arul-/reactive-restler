<?php namespace Luracast\Restler\MediaTypes;

use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Utils\Convert;

class Js extends MediaType implements ResponseMediaTypeInterface
{
    const MIME = 'text/javascript';
    const EXTENSION = 'js';

    public static $encodeOptions = 0;
    public static $callbackMethodName = 'parseResponse';
    public static $callbackOverrideQueryString = 'callback';
    public static $includeHeaders = true;

    public function encode($data, bool $humanReadable = false): string
    {
        $r = array();
        if (static::$includeHeaders) {
            $r['meta'] = array();
            foreach (headers_list() as $header) {
                list($h, $v) = explode(': ', $header, 2);
                $r['meta'][$h] = $v;
            }
        }
        $r['data'] = $data;
        if (isset($_GET[static::$callbackOverrideQueryString])) {
            static::$callbackMethodName
                = (string)$_GET[static::$callbackOverrideQueryString];
        }
        $options = static::$encodeOptions;
        if ($humanReadable) {
            $options |= JSON_PRETTY_PRINT;
        }

        return static::$callbackMethodName . '('
            . json_encode(Convert::toArray($r, true), $options) . ');';
    }
}