<?php


namespace Luracast\Restler\MediaTypes;


use JsonSerializable;
use Luracast\Restler\ArrayObject;
use Luracast\Restler\Contracts\ContainerInterface;
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
    protected $parseViewMetadata = true;
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
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(
        Restler $restler,
        ContainerInterface $container,
        StaticProperties $html,
        StaticProperties $defaults
    ) {
        $this->restler = $restler;
        if (!$html->viewPath) {
            $html->viewPath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'views';
        }
        $this->html = $html;
        $this->defaults = $defaults;
        $this->container = $container;
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

    public function getViewFile($fullPath = false, $includeExtension = true): string
    {
        $v = $fullPath ? $this->html->viewPath . '/' : '';
        $v .= $this->html->view;
        if ($includeExtension) {
            $v .= '.' . $this->getViewExtension();
        }
        return $v;
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
                'error' => $error,
                'restler' => $this->restler,
                'container' => $this->container
            ]);
            $data->basePath = dirname($_SERVER['SCRIPT_NAME']);
            $data->baseUrl = $this->restler->baseUrl;
            $data->currentPath = $this->restler->path;
            $api = $data->api = $this->restler->apiMethodInfo;
            $metadata = $api->metadata;
            $view = $success ? 'view' : 'errorView';
            $value = false;
            if ($this->parseViewMetadata && isset($metadata[$view])) {
                if (is_array($metadata[$view])) {
                    $this->html['view'] = $metadata[$view]['description'];
                    $value = $metadata[$view]['properties']['value'];
                } else {
                    $this->html['view'] = $metadata[$view];
                }
            } elseif (!$this->html['view']) {
                $this->html['view'] = $this->guessViewName($this->restler->path);
            }
            $data->merge($this->html['data']);
            if ($value) {
                $data = $data->nested($value);
                if (is_object($data)) {
                    $data = $data instanceof JsonSerializable
                        ? $data->jsonSerialize()
                        : get_object_vars($data);
                }
                if (!is_array($data)) {
                    $data = ['data' => $data];
                }
                $data = ArrayObject::fromArray($data);
            }
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
            $this->parseViewMetadata = false;
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

    public function php(ArrayObject $data, $debug = true)
    {
        if ($this->html->view == 'debug') {
            $this->html->viewPath = dirname(__DIR__) . '/views';
        }
        $view = $this->getViewFile(true);
        if (!is_readable($view)) {
            throw new HttpException(
                500,
                "view file `$view` is not readable. " .
                'Check for file presence and file permissions'
            );
        }
        $path = $this->html->viewPath . DIRECTORY_SEPARATOR;
        $template = function ($view) use ($data, $path) {
            $form = function () {
                return call_user_func_array(
                    'Luracast\Restler\UI\Forms::get',
                    func_get_args()
                );
            };
            if (!isset($data['form'])) {
                $data['form'] = $form;
            }
            $nav = function () {
                return call_user_func_array(
                    'Luracast\Restler\UI\Nav::get',
                    func_get_args()
                );
            };
            if (!isset($data['nav'])) {
                $data['nav'] = $nav;
            }

            $_ = function () use ($data, $path) {
                extract($data->getArrayCopy());
                $args = func_get_args();
                $task = array_shift($args);
                switch ($task) {
                    case 'read':
                        $file = $path . $args[0];
                        if (is_readable($file)) {
                            return file_get_contents($file);
                        }
                        break;
                    case 'require':
                    case 'include':
                        $file = $path . $args[0];
                        if (is_readable($file)) {
                            if (
                                isset($args[1]) &&
                                ($arrays = $data->nested($args[1]))
                            ) {
                                $str = '';
                                foreach ($arrays as $arr) {
                                    if ($arr instanceof JsonSerializable) {
                                        $arr = $arr->jsonSerialize();
                                    }
                                    if (is_array($arr)) {
                                        extract($arr);
                                    }
                                    $str .= include $file;
                                }
                                return $str;
                            } else {
                                return include $file;
                            }
                        }
                        break;
                    case 'if':
                        if (count($args) < 2) {
                            $args[1] = '';
                        }
                        if (count($args) < 3) {
                            $args[2] = '';
                        }
                        return $args[0] ? $args[1] : $args[2];
                        break;
                    default:
                        if (isset($data[$task]) && is_callable($data[$task])) {
                            return call_user_func_array($data[$task], $args);
                        }
                }
                return '';
            };
            extract($data->getArrayCopy());
            return @include $view;
        };
        $value = $template($view);
        return is_string($value) ? $value : '';
    }

    /**
     * @param ArrayObject $data
     * @param bool $debug
     * @return false|string
     * @throws Throwable
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function twig(ArrayObject $data, $debug = true)
    {
        $loader = new \Twig_Loader_Filesystem($this->html->viewPath);
        $twig = new \Twig_Environment($loader, array(
            'cache' => $this->html->cacheDirectory,
            'debug' => $debug,
            'use_strict_variables' => $debug,
        ));
        if ($debug) {
            $twig->addExtension(new \Twig_Extension_Debug());
        }

        $twig->addFunction(
            new \Twig_SimpleFunction(
                'form',
                'Luracast\Restler\UI\Forms::get',
                array('is_safe' => array('html'))
            )
        );
        $twig->addFunction(
            new \Twig_SimpleFunction(
                'form_key',
                'Luracast\Restler\UI\Forms::key'
            )
        );
        $twig->addFunction(
            new \Twig_SimpleFunction(
                'nav',
                'Luracast\Restler\UI\Nav::get'
            )
        );

        $twig->registerUndefinedFunctionCallback(function ($name) {
            if (
                isset($this->html->data[$name]) &&
                is_callable($this->html->data[$name])
            ) {
                return new \Twig_SimpleFunction(
                    $name,
                    $this->html->data[$name]
                );
            }
            return false;
        });
        $template = $twig->loadTemplate($this->getViewFile());
        $data = $data->getArrayCopy() ?? [];
        return $template->render($data) ?? '';
    }

    public function handlebar(ArrayObject $data, $debug = true)
    {
        return $this->mustache($data, $debug);
    }

    public function mustache(ArrayObject $data, $debug = true)
    {
        if (!isset($data['nav'])) {
            //$data['nav'] = array_values(Nav::get()); //TODO get nav to work
        }
        $options = array(
            'loader' => new \Mustache_Loader_FilesystemLoader(
                $this->html->viewPath,
                array('extension' => $this->getViewExtension())
            ),
            'helpers' => array(
                'form' => function ($text, \Mustache_LambdaHelper $m) {
                    $params = explode(',', $m->render($text));
                    return call_user_func_array(
                        'Luracast\Restler\UI\Forms::get',
                        $params
                    );
                },
            )
        );
        if (!$debug) {
            $options['cache'] = $this->html->cacheDirectory;
        }
        $m = new \Mustache_Engine($options);
        return $m->render($this->getViewFile(), $data);
    }
}