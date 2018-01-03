<?php namespace Luracast\Restler\MediaTypes;


use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Utils\Convert;
use Symfony\Component\Yaml\Yaml as Y;

class Yaml extends DependentMediaType implements RequestMediaTypeInterface, ResponseMediaTypeInterface
{
    const MIME = 'text/plain';
    const EXTENSION = 'yaml';

    /**
     * @return array {@type associative}
     *               CLASS_NAME => vendor/project:version
     */
    public function dependencies()
    {
        return ['Symfony\Component\Yaml\Yaml' => 'symfony/yaml:*'];
    }

    public function decode($string)
    {
        return Y::parse($string);
    }

    public function encode($data, $humanReadable = false)
    {
        return @Y::dump(Convert::toArray($data));
    }
}