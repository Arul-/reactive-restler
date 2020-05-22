<?php

namespace Luracast\Restler\MediaTypes;


use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Proxies\UploadedFile;
use Luracast\Restler\Utils\Convert;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

class Upload extends MediaType implements RequestMediaTypeInterface
{
    const MIME = 'multipart/form-data';
    const EXTENSION = 'post';
    public static $errors = [
        UPLOAD_ERR_OK => false,
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the maximum allowed size',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the maximum allowed size',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    /**
     * use it if you need to restrict uploads based on file type
     * setting it as an empty array allows all file types
     * default is to allow only png and jpeg images
     *
     * @var array
     */
    public static $allowedMimeTypes = array('image/jpeg', 'image/png');
    /**
     * use it to restrict uploads based on file size
     * set it to 0 to allow all sizes
     * please note that it upload restrictions in the server
     * takes precedence so it has to be lower than or equal to that
     * default value is 1MB (1024x1024)bytes
     * usual value for the server is 8388608
     *
     * @var int
     */
    public static $maximumFileSize = 1048576;
    /**
     * Your own validation function for validating each uploaded file
     * it can return false or throw an exception for invalid file
     * use anonymous function / closure in PHP 5.3 and above
     * use function name in other cases
     *
     * @var Callable
     */
    public static $customValidationFunction;
    /**
     * Since exceptions are triggered way before at the `get` stage
     *
     * @var bool
     */
    public static $suppressExceptionsAsError = false;
    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function __construct(Convert $convert, ServerRequestInterface $request)
    {
        parent::__construct($convert);
        $this->request = $request;
    }

    /**
     * @param string $data
     * @return array
     * @throws HttpException
     */
    public function decode(string $data)
    {
        $doMimeCheck = !empty(self::$allowedMimeTypes);
        $doSizeCheck = self::$maximumFileSize ? true : false;
        //validate
        $files = $this->request->getUploadedFiles();
        /** @var UploadedFileInterface $file */
        $result = [];
        foreach ($files as $file) {
            $file = static::checkFile($file, $doMimeCheck, $doSizeCheck);
            $result[$file->getClientFilename()] = $file;
        }
        parse_str($data, $result);
        return UrlEncoded::decoderTypeFix($result);
    }

    /**
     * @param UploadedFileInterface $file
     * @param bool $doMimeCheck
     * @param bool $doSizeCheck
     * @throws HttpException
     */
    protected static function checkFile(
        UploadedFileInterface $file,
        $doMimeCheck = false,
        $doSizeCheck = false
    ): UploadedFileInterface {
        try {
            if ($error = $file->getError()) {
                throw new HttpException($error > 5 ? 500 : 413, static::$errors[$error]);
            }
            if ($doMimeCheck && !(in_array($file->getClientMediaType(), self::$allowedMimeTypes))) {
                throw new HttpException(403, "File type ({$file->getClientMediaType()}) is not supported.");
            }
            if ($doSizeCheck && $file->getSize() > self::$maximumFileSize) {
                throw new HttpException(413, "Uploaded file ({$file->getClientFilename()}) is too big.");
            }
            if (self::$customValidationFunction) {
                if (!call_user_func(self::$customValidationFunction, $file)) {
                    throw new HttpException(403, "File ({$file->getClientFilename()}) is not supported.");
                }
            }
        } catch (HttpException $e) {
            if (static::$suppressExceptionsAsError) {
                $file = new UploadedFile($file);
                $file->setError($e->getCode() == 413 ? 1 : 6);
                $file->exception = $e;
            } else {
                throw $e;
            }
        }
        return $file;
    }
}
