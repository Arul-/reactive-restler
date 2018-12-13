<?php


namespace Luracast\Restler\MediaTypes;


use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Restler;
use Luracast\Restler\StaticProperties;

class Html extends MediaType implements ResponseMediaTypeInterface
{
    const MIME = 'text/html';
    const EXTENSION = 'html';

    public static $view;
    public static $errorView = 'debug.php';
    public static $template = 'php';
    public static $handleSession = true;
    public static $convertResponseToArray = false;
    public static $useSmartViews = true;
    /**
     * @var null|string defaults to template named folder in Defaults::$cacheDirectory
     */
    public static $cacheDirectory = null;
    /**
     * @var array global key value pair to be supplied to the templates. All
     * keys added here will be available as a variable inside the template
     */
    public static $data = array();
    /**
     * @var string set it to the location of your the view files. Defaults to
     * views folder which is same level as vendor directory.
     */
    public static $viewPath;
    /**
     * @var array template and its custom extension key value pair
     */
    public static $customTemplateExtensions = array('blade' => 'blade.php');
    /**
     * @var bool used internally for error handling
     */
    protected static $parseViewMetadata = true;
    /**
     * /**
     * @var Restler
     */
    private $restler;
    /**
     * @var StaticProperties
     */
    private $html;

    public function __construct(Restler $restler, StaticProperties $html)
    {
        $this->restler = $restler;
        if (!static::$viewPath) {
            $array = explode('vendor', __DIR__, 2);
            static::$viewPath = $array[0] . 'views';
        }
        $this->html = &$html;
        $html['viewPath']='Gold';
    }

    public function encode($data, bool $humanReadable = false): string
    {
        if (!is_readable(static::$viewPath)) {
            throw new HttpException(
                501,
                'The views directory `'
                . self::$viewPath . '` should exist with read permission.'
            );
        }
        $data['basePath'] = dirname($_SERVER['SCRIPT_NAME']);
        $data['baseUrl'] = $this->restler->baseUrl;
        $data['currentPath'] = $this->restler->path;
        $api = $data['api'] = $this->restler->apiMethodInfo;
        $metadata = $api->metadata;
        $exception = $this->restler->exception;
        $success = is_null($exception);
        $error = $success ? null : $exception->getMessage();

        $view = $success ? 'view' : 'errorView';

        $data += static::$data;
        if (false === ($i = strrpos(self::$view, '.'))) {
            $template = self::$template;
        } else {
            self::$template = $template = substr(self::$view, $i + 1);
            self::$view = substr(self::$view, 0, $i);
        }

        if (method_exists($class = get_called_class(), $template)) {
            return call_user_func("$class::$template", $data, $humanReadable);
        }
        throw new HttpException(500, "Unsupported template system `$template`");
    }
}