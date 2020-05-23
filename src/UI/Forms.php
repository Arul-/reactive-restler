<?php

namespace Luracast\Restler\UI;

use Luracast\Restler\Contracts\FilterInterface;
use Luracast\Restler\Data\Param;
use Luracast\Restler\Data\Route;
use Luracast\Restler\Data\Type;
use Luracast\Restler\Defaults;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\MediaTypes\Upload;
use Luracast\Restler\MediaTypes\UrlEncoded;
use Luracast\Restler\ResponseHeaders;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;
use Luracast\Restler\StaticProperties;
use Luracast\Restler\UI\Tags as T;
use Luracast\Restler\Utils\CommentParser;
use Luracast\Restler\Utils\Text;
use Luracast\Restler\Utils\Validator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;


/**
 * Utility class for automatically generating forms for the given http method
 * and api url
 *
 * @category   Framework
 * @package    Restler
 * @author     R.Arul Kumaran <arul@luracast.com>
 */
class Forms implements FilterInterface
{
    const FORM_KEY = 'form_key';

    public static $filterFormRequestsOnly = false;
    public static $excludedPaths = [];
    public static $style;
    /**
     * @var bool should we fill up the form using given data?
     */
    public static $preFill = true;
    protected $inputTypes = array(
        'hidden',
        'password',
        'button',
        'image',
        'file',
        'reset',
        'submit',
        'search',
        'checkbox',
        'radio',
        'email',
        'text',
        'color',
        'date',
        'datetime',
        'datetime-local',
        'email',
        'month',
        'number',
        'range',
        'search',
        'tel',
        'time',
        'url',
        'week',
    );
    protected $fileUpload = false;
    private $key = array();
    /**
     * @var Route;
     */
    private $route;
    /**
     * @var Route
     */
    private $currentRoute;
    /**
     * @var Restler
     */
    private $restler;
    /**
     * @var StaticProperties
     */
    private $forms;

    public function __construct(Restler $restler, Route $route, StaticProperties $forms)
    {
        $this->restler = $restler;
        $this->currentRoute = $route;
        $this->forms = $forms;
    }

    /**
     * Get the form
     *
     * @param string $method http method to submit the form
     * @param string $action relative path from the web root. When set to null
     *                         it uses the current api method's path
     * @param bool $dataOnly if you want to render the form yourself use this
     *                         option
     * @param string $prefix used for adjusting the spacing in front of
     *                         form elements
     * @param string $indent used for adjusting indentation
     *
     * @return array|T
     *
     * @throws HttpException
     */
    public function get($method = 'POST', $action = null, $dataOnly = false, $prefix = '', $indent = '    ')
    {
        if (!$this->forms->style) {
            $this->forms->style = FormStyles::$html;
        }

        try {
            if (is_null($action)) {
                $action = $this->currentRoute->path;
            }
            $current = $this->currentRoute;
            if ((($method == $current->httpMethod) && ($action == $current->path))) {
                $this->route = $route = $this->currentRoute;
            } else {
                $this->route = $route = Router::find(
                    trim($action, '/'),
                    $method,
                    $this->restler->requestedApiVersion,
                    []
                );
            }
        } catch (HttpException $e) {
            //echo $e->getErrorMessage();
            $route = false;
        }
        if (!$route) {
            throw new HttpException(500, 'invalid action path for form `' . $method . ' ' . $action . '`');
        }
        $r = static::fields($route, $dataOnly);
        if ($method != 'GET' && $method != 'POST') {
            if ($dataOnly) {
                $r[] = array(
                    'tag' => 'input',
                    'name' => Defaults::$httpMethodOverrideProperty,
                    'type' => 'hidden',
                    'value' => 'method',
                );
            } else {
                $r[] = T::input()
                    ->name(Defaults::$httpMethodOverrideProperty)
                    ->value($method)
                    ->type('hidden');
            }

            $method = 'POST';
        }
        if (session_id() != '') {
            $form_key = static::key($method, $action);
            if ($dataOnly) {
                $r[] = array(
                    'tag' => 'input',
                    'name' => static::FORM_KEY,
                    'type' => 'hidden',
                    'value' => 'hidden',
                );
            } else {
                $key = T::input()
                    ->name(static::FORM_KEY)
                    ->type('hidden')
                    ->value($form_key);
                $r[] = $key;
            }
        }

        $s = [
            'tag' => 'button',
            'type' => 'submit',
            'label' => $m['return'][CommentParser::$embeddedDataName]['label']
                ?? 'Submit'
        ];

        if (!$dataOnly) {
            $s = Emmet::make($this->style('submit', $route->return), $s);
        }
        $r[] = $s;
        $t = [
            'action' => $this->restler->baseUrl . trim($action, '/'),
            'method' => $method,
        ];
        if ($this->fileUpload) {
            $this->fileUpload = false;
            $t['enctype'] = 'multipart/form-data';
        }
        if (isset($m[CommentParser::$embeddedDataName])) {
            $t += $m[CommentParser::$embeddedDataName];
        }
        if (!$dataOnly) {
            $t = Emmet::make($this->style('form', $route->return), $t);
            $t->prefix = $prefix;
            $t->indent = $indent;
            $t[] = $r;
        } else {
            $t['fields'] = $r;
        }
        return $t;
    }

    public function fields(Route $route, bool $dataOnly = false)
    {
        $r = [];
        $values = $route->getArguments();
        foreach ($route->parameters as $parameter) {
            if (!$this->fieldable($parameter)) {
                continue;
            }
            $value = $values[$parameter->index] ?? null;
            if (!$this->fillable($parameter, $value)) {
                $value = null;
            }
            if (!empty($parameter->children)) {
                $t = Emmet::make($this->style('fieldset', $parameter), ['label' => $parameter->label]);
                /**
                 * @var string|int $key
                 * @var  Param $child
                 */
                foreach ($parameter->children as $key => $child) {
                    if (!$this->fieldable($child)) {
                        continue;
                    }
                    $childValue = $value[$key] ?? null;
                    if (!$this->fillable($child, $childValue)) {
                        $childValue = null;
                    }
                    $child = clone $child;
                    $child->name = sprintf("%s[%s]", $parameter->name, $child->name);
                    $t[] = $this->field($child, $childValue, false);
                }
                $r[] = $t;
            } else {
                $f = $this->field($parameter, $value, false);
                $r[] = $f;
            }
        }
        return $r;
    }

    private function fieldable(Param $parameter): bool
    {
        return Param::FROM_PATH !== $parameter->from && Param::FROM_HEADER !== $parameter->from;
    }

    private function fillable(Param $param, $value): bool
    {
        return $this->forms->preFill && is_scalar($value) ||
            ('array' == $param->type && is_array($value)) ||
            (is_object($value) && $param->type == get_class($value));
    }

    public function style(string $name, ?Type $param, ?string $default = null)
    {
        if ($param) {
            if (isset($param->{$name})) {
                return $param->{$name};
            }
            if (isset($param->rules) && isset($param->rules[$name])) {
                return $param->rules[$name];
            }
        }
        return $this->forms->style[$name] ?? $default;
    }

    /**
     * @param Param $p
     *
     * @param mixed $value
     * @param bool $dataOnly
     * @return array|T
     */
    public function field(Param $p, $value, bool $dataOnly = false)
    {
        if (is_string($value)) {
            //prevent XSS attacks
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML401, 'UTF-8');
        }
        $type = $p->field ?: static::guessFieldType($p);
        $tag = in_array($type, $this->inputTypes)
            ? 'input' : $type;
        $options = array();
        $name = $p->name;
        $multiple = null;
        if ($p->type == 'array' && $p->contentType != 'associative') {
            $name .= '[]';
            $multiple = true;
        }
        if ($p->choice) {
            foreach ($p->choice as $i => $choice) {
                $option = array('name' => $name, 'value' => $choice);
                $option['text'] = isset($p->rules['select'][$i])
                    ? $p->rules['select'][$i]
                    : $choice;
                if ($choice == $value) {
                    $option['selected'] = true;
                }
                $options[] = $option;
            }
        } elseif ($p->type == 'boolean' || $p->type == 'bool') {
            if (Text::beginsWith($type, 'radio') || Text::beginsWith($type, 'select')) {
                $options[] = array(
                    'name' => $p->name,
                    'text' => ' Yes ',
                    'value' => 'true'
                );
                $options[] = array(
                    'name' => $p->name,
                    'text' => ' No ',
                    'value' => 'false'
                );
                if ($value || $p->default) {
                    $options[0]['selected'] = true;
                }
            } else { //checkbox
                $r = array(
                    'tag' => $tag,
                    'name' => $name,
                    'type' => $type,
                    'label' => $p->label,
                    'value' => 'true',
                    'default' => $p->default,
                );
                $r['text'] = 'Yes';
                if ($p->default) {
                    $r['selected'] = true;
                }
                if (isset($p->rules)) {
                    $r += $p->rules;
                }
            }
        }
        if (empty($r)) {
            $r = array(
                'tag' => $tag,
                'name' => $name,
                'type' => $type,
                'label' => $p->label,
                'value' => $value,
                'default' => $p->default,
                'options' => & $options,
                'multiple' => $multiple,
            );
            if (isset($p->rules)) {
                $r += $p->rules;
            }
        }
        if ('file' == $type) {
            $this->fileUpload = true;
            if (empty($r['accept'])) {
                $r['accept'] = implode(', ', Upload::supportedMediaTypes());
            }
        }
        if (!empty(Validator::$exceptions[$name]) && $this->route->url == $this->restler->path) {
            $r['error'] = 'has-error';
            $r['message'] = Validator::$exceptions[$p->name]->getMessage();
        }

        if (true === $p->required) {
            $r['required'] = 'required';
        }
        if (isset($p->rules['autofocus'])) {
            $r['autofocus'] = 'autofocus';
        }
        /*
        echo "<pre>";
        print_r($r);
        echo "</pre>";
        */
        if ($dataOnly) {
            return $r;
        }
        if (isset($p->rules['form'])) {
            return Emmet::make($p->rules['form'], $r);
        }
        $t = Emmet::make($this->style($type, $p) ?: $this->style($tag, $p), $r);
        return $t;
    }

    protected function guessFieldType(Param $p, $type = 'type')
    {
        if (in_array($p->$type, $this->inputTypes)) {
            return $p->$type;
        }
        if ($p->choice) {
            return $p->type == 'array' ? 'checkbox' : 'select';
        }
        switch ($p->$type) {
            case 'boolean':
                return 'radio';
            case 'int':
            case 'number':
            case 'float':
                return 'number';
            case UploadedFileInterface::class:
                return 'file';
            case 'array':
                return $this->guessFieldType($p, 'contentType');
        }
        if ($p->name == 'password') {
            return 'password';
        }
        return 'text';
    }

    /**
     * Get the form key
     *
     * @param string $method http method for form key
     * @param string $action relative path from the web root. When set to null
     *                         it uses the current api method's path
     *
     * @return string generated form key
     */
    public function key($method = 'POST', $action = null)
    {
        if (is_null($action)) {
            $action = $this->restler->path;
        }
        $target = "$method $action";
        if (empty($this->key[$target])) {
            $this->key[$target] = md5($target . User::getIpAddress() . uniqid(mt_rand()));
        }
        $_SESSION[static::FORM_KEY] = $this->key;
        return $this->key[$target];
    }

    /**
     * Access verification method.
     *
     * API access will be denied when this method returns false
     *
     * @param ServerRequestInterface $request
     * @param ResponseHeaders $responseHeaders
     *
     * @return boolean true when api access is allowed false otherwise
     *
     * @throws HttpException 403 security violation
     */
    public function _isAllowed(ServerRequestInterface $request, ResponseHeaders $responseHeaders): bool
    {
        if (session_id() == '') {
            session_start();
        }
        /** @var Restler $restler */
        $restler = $this->restler;
        $url = $restler->path;
        foreach (static::$excludedPaths as $exclude) {
            if (empty($exclude)) {
                if ($url == $exclude) {
                    return true;
                }
            } elseif (Text::beginsWith($url, $exclude)) {
                return true;
            }
        }
        $check = static::$filterFormRequestsOnly
            ? $restler->requestFormat instanceof UrlEncoded || $restler->requestFormat instanceof Upload
            : true;
        if (!empty($_POST) && $check) {
            if (
                isset($_POST[static::FORM_KEY]) &&
                ($target = $restler->requestMethod . ' ' . $restler->path) &&
                isset($_SESSION[static::FORM_KEY][$target]) &&
                $_POST[static::FORM_KEY] == $_SESSION[static::FORM_KEY][$target]
            ) {
                return true;
            }
            throw new HttpException(403, 'Insecure form submission');
        }
        return true;
    }
}
