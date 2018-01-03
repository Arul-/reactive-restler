<?php namespace Luracast\Restler\MediaTypes;


use Luracast\Restler\Contracts\MediaTypeInterface;
use Exception;
use Luracast\Restler\HttpException;

abstract class MediaType implements MediaTypeInterface
{
    /**
     * override in the extending class
     */
    const MIME = 'text/plain';
    /**
     * override in the extending class
     */
    const EXTENSION = 'txt';

    protected $mime;
    protected $extension;
    protected $charset = 'utf-8';

    /**
     * Get MIME type => Extension mappings as an associative array
     *
     * @return array list of mime strings for the MediaType
     * @example array('application/json'=>'json');
     */
    public static function supportedMediaTypes()
    {
        return [static::MIME => static::EXTENSION];
    }

    public function mediaType(string $type = null)
    {
        if (is_null($type)) {
            return $this->mime ?: static::MIME;
        }
        $types = static::supportedMediaTypes();
        if (isset($types[$type])) {
            $this->mime = $type;
            $this->extension = $types[$type];
            return $this;
        }
        throw new HttpException("Invalid Media Type `$type`");
    }

    public function extension(string $extension = null)
    {
        if (is_null($extension)) {
            return $this->extension ?: static::EXTENSION;
        }
        if ($mime = array_search($extension, static::supportedMediaTypes())) {
            $this->mime = $mime;
            $this->extension = $extension;
            return $this;
        }
        throw new HttpException("Invalid Extension `$extension`");
    }

    public function charset(string $charset = null)
    {
        if (is_null($charset)) {
            return $this->charset;
        }
        $this->charset = $charset;
        return $this;
    }
}