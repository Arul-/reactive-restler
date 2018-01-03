<?php namespace Luracast\Restler\MediaTypes;

use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use ZendAmf\Parser\Amf3\Deserializer;
use ZendAmf\Parser\Amf3\Serializer;
use ZendAmf\Parser\InputStream;
use ZendAmf\Parser\OutputStream;

class Amf extends Dependent implements RequestMediaTypeInterface, ResponseMediaTypeInterface
{

    /**
     * @return array {@type associative}
     *               CLASS_NAME => vendor/project:version
     */
    public function dependencies()
    {
        return [
            'ZendAmf\Parser\Amf3\Deserializer' => 'zendframework/zendamf:dev-master',
        ];
    }

    public function encode($data, bool $humanReadable = false): string
    {

        $stream = new OutputStream();
        $serializer = new Serializer($stream);
        $serializer->writeTypeMarker($data);

        return $stream->getStream();
    }

    public function decode($data)
    {
        $stream = new InputStream(substr($data, 1));
        $deserializer = new Deserializer($stream);

        return $deserializer->readTypeMarker();
    }
}