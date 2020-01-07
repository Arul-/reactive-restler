<?php namespace Luracast\Restler\MediaTypes;


use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Utils\Convert;

use CFPropertyList\CFTypeDetector;
use CFPropertyList\CFPropertyList;

class Plist extends Dependent implements RequestMediaTypeInterface, ResponseMediaTypeInterface
{
    public static $compact = null;

    /**
     * @return array {@type associative}
     *               CLASS_NAME => vendor/project:version
     */
    public function dependencies()
    {
        return [
            'CFPropertyList\CFPropertyList' => 'rodneyrehm/plist:dev-master'
        ];
    }

    public static function supportedMediaTypes()
    {
        return [
            'application/xml' => 'plist',
            'application/x-plist' => 'plist',
        ];
    }

    public function mediaType(string $type = null)
    {
        if (!is_null($type)) {
            static::$compact = $type == 'application/x-plist';
        }

        return parent::mediaType($type);
    }

    public function encode($data, array &$responseHeaders, bool $humanReadable = false)
    {
        if (!isset(self::$compact)) {
            self::$compact = !$humanReadable;
        }
        $plist = new CFPropertyList ();
        $td = new CFTypeDetector ();
        $guessedStructure = $td->toCFType(
            $this->convert->toArray($data)
        );
        $plist->add($guessedStructure);

        return self::$compact
            ? $plist->toBinary()
            : $plist->toXML(true);
    }

    public function decode(string $data)
    {
        $plist = new CFPropertyList ();
        $plist->parse($data);

        return $plist->toArray();
    }
}
