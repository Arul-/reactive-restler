<?php


namespace Luracast\Restler\MediaTypes;


use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Illuminate\View\View;
use JsonSerializable;
use Luracast\Restler\ArrayObject;
use Luracast\Restler\Contracts\ContainerInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Contracts\SessionInterface;
use Luracast\Restler\Defaults;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\ResponseHeaders;
use Luracast\Restler\Restler;
use Luracast\Restler\StaticProperties;
use Luracast\Restler\UI\Forms;
use Luracast\Restler\UI\Nav;
use Luracast\Restler\Utils\Convert;
use Luracast\Restler\Utils\Text;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class Html extends MediaType implements ResponseMediaTypeInterface
{
    const MIME = 'text/html';
    const EXTENSION = 'html';

    const DEPENDENCIES = [
        'blade' => ['Illuminate\View\View', 'illuminate/view:^8 || ^7'],
        'twig' => ['Twig\Environment', 'twig/twig:^3'],
        'mustache' => ['Mustache_Engine', 'mustache/mustache:^2"'],
    ];

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
    public static $data = [];
    /**
     * @var string set it to the location of your the view files. Defaults to
     * views folder which is same level as vendor directory.
     */
    public static $viewPath;
    /**
     * @var array template and its custom extension key value pair
     */
    public static $customTemplateExtensions = ['blade' => 'blade.php'];
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
    /**
     * @var SessionInterface
     */
    private $session;
    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function __construct(
        Restler $restler,
        SessionInterface $session,
        ContainerInterface $container,
        ServerRequestInterface $request,
        StaticProperties $html,
        StaticProperties $defaults,
        Convert $convert
    ) {
        parent::__construct($convert);
        if (!static::$cacheDirectory) {
            static::$cacheDirectory = Defaults::$cacheDirectory;
        }
        if (!static::$viewPath) {
            static::$viewPath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'views';
        }
        //============ SESSION MANAGEMENT =============//
        if ($html->handleSession) {
            $key = 'flash';
            if ($session->start() && $session->hasFlash($key)) {
                $html->data['flash'] = $session->flash($key);
                $session->unsetFlash($key);
            }
        }
        $this->restler = $restler;
        $this->session = $session;
        $this->container = $container;
        $this->html = $html;
        $this->defaults = $defaults;
        $this->request = $request;
    }

    public function encode($data, ResponseHeaders $responseHeaders, bool $humanReadable = false)
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
            $data = ArrayObject::fromArray(
                [
                    'response' => $data,
                    'success' => $success,
                    'error' => $error,
                    'restler' => $this->restler,
                    'container' => $this->container,
                    'baseUrl' => $this->restler->baseUrl,
                    'currentPath' => $this->restler->path,
                ]
            );
            $rpath = $this->request->getUri()->getPath();
            $data->resourcePathNormalizer = Text::endsWith($rpath, '/') || Text::endsWith($rpath, 'index.html')
                ? '../' : './';
            $data->basePath = $data->baseUrl->getPath();
            $metadata = $data->api = $this->restler->route;
            $view = $success ? 'view' : 'errorView';
            $value = false;
            if ($this->parseViewMetadata && isset($metadata->{$view})) {
                if (is_array($metadata->{$view})) {
                    $this->html['view'] = $metadata->{$view}['description'];
                    $value = $metadata->{$view}['properties']['value'];
                } else {
                    $this->html['view'] = $metadata->{$view};
                }
            } elseif (!$this->html['view']) {
                $file = explode('/', $this->restler->path);
                $file = end($file);
                $this->html['view'] = $this->guessViewName($file);
            }
            $data->merge(ArrayObject::fromArray($this->html['data']));
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
            if (!file_exists($this->html['cacheDirectory'])) {
                if (!mkdir($this->html['cacheDirectory'], 0770, true)) {
                    throw new HttpException(
                        500,
                        'Unable to create cache directory `' . $this->html['cacheDirectory'] . '`'
                    );
                }
            }
            if (method_exists($class = get_called_class(), $template)) {
                if (isset(self::DEPENDENCIES[$template])) {
                    [$className, $package] = self::DEPENDENCIES[$template];
                    if (!class_exists($className, true)) {
                        throw new HttpException(
                            500,
                            get_called_class() . ' has external dependency. Please run `composer require ' .
                            $package . '` from the project root. Read https://getcomposer.org for more info'
                        );
                    }
                }
                return call_user_func("$class::$template", $data, $humanReadable);
            }
            throw new HttpException(500, "Unsupported template system `$template`");
        } catch (Throwable $throwable) {
            $this->parseViewMetadata = false;
            $this->reset();
            throw $throwable;
        }
    }

    public function guessViewName($path)
    {
        if (empty($path)) {
            $path = 'index';
        } elseif (strpos($path, '/')) {
            $path .= '/index';
        }
        $file = $this->html['viewPath'] . '/' . $path . '.' . $this->getViewExtension();
        $this->html->data['guessedView'] = $file;
        return $this->html['useSmartViews'] && is_readable($file)
            ? $path
            : $this->html->errorView;
    }

    public function getViewExtension()
    {
        return $this->html['customTemplateExtensions'][$this->html['template']] ?? $this->html['template'];
    }

    private function reset()
    {
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

    public function getViewFile($fullPath = false, $includeExtension = true): string
    {
        $v = $fullPath ? $this->html->viewPath . '/' : '';
        $v .= $this->html->view;
        if ($includeExtension) {
            $v .= '.' . $this->getViewExtension();
        }
        return $v;
    }

    /**
     * @param ArrayObject $data
     * @param bool $debug
     * @return false|string
     * @throws Throwable
     */
    public function twig(ArrayObject $data, $debug = true)
    {
        $loader = new FilesystemLoader($this->html->viewPath);
        $twig = new Environment(
            $loader, [
                'cache' => static::$cacheDirectory ?? false,
                'debug' => $debug,
                'use_strict_variables' => $debug,
            ]
        );
        if ($debug) {
            $twig->addExtension(new DebugExtension());
        }

        $twig->addFunction(
            new TwigFunction(
                'form',
                'Luracast\Restler\UI\Forms::get',
                ['is_safe' => ['html']]
            )
        );
        $twig->addFunction(
            new TwigFunction(
                'form_key',
                'Luracast\Restler\UI\Forms::key'
            )
        );
        $twig->addFunction(
            new TwigFunction(
                'nav',
                'Luracast\Restler\UI\Nav::get'
            )
        );

        $twig->registerUndefinedFunctionCallback(
            function ($name) {
                if (
                    isset($this->html->data[$name]) &&
                    is_callable($this->html->data[$name])
                ) {
                    return new TwigFunction(
                        $name,
                        $this->html->data[$name]
                    );
                }
                return false;
            }
        );
        $template = $twig->load($this->getViewFile());
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
        $options = [
            'loader' => new \Mustache_Loader_FilesystemLoader(
                $this->html->viewPath,
                ['extension' => $this->getViewExtension()]
            ),
            'helpers' => [
                'form' => function ($text, \Mustache_LambdaHelper $m) {
                    $params = explode(',', $m->render($text));
                    return call_user_func_array(
                        'Luracast\Restler\UI\Forms::get',
                        $params
                    );
                },
            ]
        ];
        if (!$debug) {
            $options['cache'] = $this->html->cacheDirectory;
        }
        $m = new \Mustache_Engine($options);
        return $m->render($this->getViewFile(), $data);
    }

    public function blade(ArrayObject $data, $debug = true)
    {
        $resolver = new EngineResolver();
        $filesystem = new Filesystem();
        $compiler = new BladeCompiler($filesystem, $this->html->cacheDirectory);
        $engine = new CompilerEngine($compiler);
        $resolver->register(
            'blade',
            function () use ($engine) {
                return $engine;
            }
        );
        $phpEngine = new PhpEngine($filesystem);
        $resolver->register(
            'php',
            function () use ($phpEngine) {
                return $phpEngine;
            }
        );

        /** @var Restler $restler */
        $restler = $this->restler;

        //Lets expose shortcuts for our classes
        spl_autoload_register(
            function ($className) use ($restler) {
                if (isset($restler->apiMethodInfo->metadata['scope'][$className])) {
                    return class_alias($restler->apiMethodInfo->metadata['scope'][$className], $className);
                }
                if (isset(Defaults::$aliases[$className])) {
                    return class_alias(Defaults::$aliases[$className], $className);
                }
                return false;
            },
            true,
            true
        );

        $viewFinder = new FileViewFinder($filesystem, [$this->html->viewPath]);
        $factory = new Factory($resolver, $viewFinder, new Dispatcher());
        $path = $viewFinder->find($this->html->view);
        $data->forms = $this->container->make(Forms::class);
        $data->nav = $this->container->make(Nav::class);
        $view = new View($factory, $engine, $this->html->view, $path, $data);
        $factory->callCreator($view);
        return $view->render();
    }
}
