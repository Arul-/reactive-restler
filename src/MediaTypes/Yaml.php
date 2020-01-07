<?php namespace Luracast\Restler\MediaTypes;


use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Utils\Convert;
use Symfony\Component\Yaml\Yaml as Y;

class Yaml extends Dependent implements RequestMediaTypeInterface, ResponseMediaTypeInterface
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

    public function decode(string $data)
    {
        return Y::parse($data);
    }

    public function encode($data, array &$responseHeaders, bool $humanReadable = false)
    {
        return @Y::dump($this->convert->toArray($data));
    }
}
