<?php

namespace Luracast\Restler\MediaTypes;


use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\ResponseHeaders;
use Luracast\Restler\Utils\Convert;
use Psr\Http\Message\ServerRequestInterface;

class UrlEncoded extends MediaType implements RequestMediaTypeInterface, ResponseMediaTypeInterface
{

    const MIME = 'application/x-www-form-urlencoded';
    const EXTENSION = 'post';
    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function __construct(Convert $convert, ServerRequestInterface $request)
    {
        parent::__construct($convert);
        $this->request = $request;
    }

    public function encode($data, ResponseHeaders $responseHeaders, bool $humanReadable = false)
    {
        return http_build_query(static::encoderTypeFix($data));
    }

    public function decode(string $data)
    {
        $r = $this->request->getParsedBody();
        if (empty($r)) {
            parse_str($data, $r);
        }
        return self::decoderTypeFix($r);
    }

    public static function encoderTypeFix(array $data)
    {
        foreach ($data as $k => $v) {
            if (is_bool($v)) {
                $data[$k] = $v = $v ? 'true' : 'false';
            } elseif (is_array($v)) {
                $data[$k] = $v = static::decoderTypeFix($v);
            }
        }
        return $data;
    }

    public static function decoderTypeFix(array $data)
    {
        foreach ($data as $k => $v) {
            if ($v === 'true' || $v === 'false') {
                $data[$k] = $v = $v === 'true';
            } elseif (is_array($v)) {
                $data[$k] = $v = static::decoderTypeFix($v);
            } elseif (empty($v) && $v != 0) {
                unset($data[$k]);
            }
        }
        return $data;
    }
}
