<?php


namespace Luracast\Restler\Proxies;


use Exception;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFile implements UploadedFileInterface
{
    /**
     * @var UploadedFileInterface
     */
    private $proxy;

    /**
     * @var int|null
     */
    private $error = null;

    /**
     * @var Exception|null
     */
    public $exception = null;

    public function __construct(UploadedFileInterface $proxy)
    {
        $this->proxy = $proxy;
    }

    public function getStream()
    {
        return $this->proxy->getStream();
    }

    public function moveTo($targetPath)
    {
        return $this->proxy->moveTo($targetPath);
    }

    public function getSize()
    {
        return $this->proxy->getSize();
    }

    public function getError()
    {
        return $this->error ?? $this->proxy->getError();
    }

    public function getClientFilename()
    {
        return $this->proxy->getClientFilename();
    }

    public function getClientMediaType()
    {
        return $this->proxy->getClientMediaType();
    }

    public function setError(int $error)
    {
        $this->error = $error;
    }
}
