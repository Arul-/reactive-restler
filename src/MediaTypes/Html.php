<?php


namespace Luracast\Restler\MediaTypes;


use Luracast\Restler\ArrayObject;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Restler;
use Luracast\Restler\StaticProperties;
use Throwable;

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
    /**
     * @var StaticProperties
     */
    private $defaults;

    public function __construct(Restler $restler, StaticProperties $html, StaticProperties $defaults)
    {
        $this->restler = $restler;
        if (!$html->viewPath) {
            $html->viewPath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'views';
        }
        $this->html = $html;
        $this->defaults = $defaults;
    }

    public function guessViewName($path)
    {
        if (empty($path)) {
            $path = 'index';
        } elseif (strpos($path, '/')) {
            $path .= '/index';
        }
        $file = $this->html['viewPath'] . '/' . $path . '.' . $this->getViewExtension();

        return $this->html['useSmartViews'] && is_readable($file)
            ? $path
            : $this->html->errorView;
    }

    public function getViewExtension()
    {
        return $this->html['customTemplateExtensions'][$this->html['template']] ?? $this->html['template'];
    }

    public function encode($data, bool $humanReadable = false): string
    {
        try {
            if (!is_readable($this->html->viewPath)) {
                throw new HttpException(
                    501,
                    'The views directory `'
                    . $this->html->viewPath . '` should exist with read permission.'
                );
            }
            $exception = $this->restler->exception;
            $success = is_null($exception);
            $error = $success ? null : $exception->getMessage();
            $data = ArrayObject::fromArray([
                'response' => $data,
                'success' => $success,
                'error' => $error
            ]);
            $data->basePath = dirname($_SERVER['SCRIPT_NAME']);
            $data->baseUrl = $this->restler->baseUrl;
            $data->currentPath = $this->restler->path;
            $api = $data->api = $this->restler->apiMethodInfo;
            $metadata = $api->metadata;
            $view = $success ? 'view' : 'errorView';
            $value = false;
            if ($this->html['parseViewMetadata'] && isset($metadata[$view])) {
                if (is_array($metadata[$view])) {
                    $this->html['view'] = $metadata[$view]['description'];
                    $value = $metadata[$view]['properties']['value'];
                } else {
                    $this->html['view'] = $metadata[$view];
                }
            } elseif (!$this->html['view']) {
                $this->html['view'] = $this->guessViewName($this->restler->path);
            }
            if ($value) {
                $data = $data->nested($value);
            }
            $data->merge($this->html['data']);
            if (false === ($i = strrpos($this->html['view'], '.'))) {
                $template = $this->html['template'];
            } else {
                $this->html['template'] = $template = substr($this->html['view'], $i + 1);
                $this->html['view'] = substr($this->html['view'], 0, $i);
            }
            if (!$this->html['cacheDirectory']) {
                $this->html['cacheDirectory'] = $this->defaults['cacheDirectory'] . DIRECTORY_SEPARATOR . $template;

            }
            if (!file_exists($this->html['cacheDirectory'])) {
                if (!mkdir($this->html['cacheDirectory'], 0770, true)) {
                    throw new HttpException(500,
                        'Unable to create cache directory `' . $this->html['cacheDirectory'] . '`');
                }
            }
            if (method_exists($class = get_called_class(), $template)) {
                return call_user_func("$class::$template", $data, $humanReadable);
            }
            throw new HttpException(500, "Unsupported template system `$template`");
        } catch (Throwable $throwable) {
            $this->html->parseViewMetadata = false;
            $this->reset();
            throw $throwable;
        }
    }

    private function reset()
    {
        $this->html->mime = 'text/html';
        $this->html->extension = 'html';
        $this->html->view = 'debug';
        $this->html->template = 'php';
    }
}