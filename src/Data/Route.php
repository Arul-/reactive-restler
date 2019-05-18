<?php


namespace Luracast\Restler\Data;


use Luracast\Restler\Utils\Validator;

class Route extends ValueObject
{
    const PUBLIC = 0;
    const HYBRID = 1;
    const PROTECTED_BY_COMMENT = 2;
    const PROTECTED_METHOD = 3;
    /**
     * @var string target uri
     */
    public $url;

    /**
     * @var callable
     */
    public $action;

    /**
     * @var array[Param]
     */
    public $parameters = [];

    /**
     * @var int access level
     */
    public $access = self::PUBLIC;

    public $requestMediaTypes = ['application/json'];
    public $responseMediaTypes = ['application/json'];

    /**
     * @var string
     */
    public $summary;

    /**
     * @var string
     */
    public $description;

    /**
     * @var Param
     */
    public $return;

    /**
     * @var array
     */
    public $responses = [
        //200 => [
        //  'message'=> 'OK',
        //  'type'=> Class::name,
        //  'description'=> '',
        //],
        //404 => [
        //  'message'=> 'Not Found',
        //  'type'=> Exception:name,
        //  'description'=> '',
        //],
    ];

    /**
     * @var array
     */
    private $arguments = [];

    public function addParameter(Param $parameter)
    {
        $parameter->index = count($this->parameters);
        $this->parameters[$parameter->name] = $parameter;
    }

    public function apply(array $arguments): array
    {
        $p = [];
        foreach ($this->parameters as $parameter) {
            $p[$parameter->index] = $arguments[$parameter->name]
                ?? $arguments[$parameter->index]
                ?? $parameter->default
                ?? null;
        }
        if (empty($p) && !empty($arguments)) {
            $this->arguments = array_values($arguments);
        } else {
            $this->arguments = $p;
        }
        return $p;
    }

    public function body()
    {
        return array_filter($this->parameters, function ($v) {
            return $v->from === 'body';
        });
    }

    public function call(array $arguments = null)
    {
        if (is_array($arguments)) {
            $this->apply($arguments);
        }
        foreach ($this->parameters as $parameter) {
            $i = $parameter->index;
            $this->arguments[$i] = Validator::validate($this->arguments[$i], $parameter);
        }
        //if (!is_callable($this->action)) {
        if (is_array($this->action) && count($this->action) && class_exists($this->action[0])) {
            $this->action[0] = new $this->action[0];
        }
        //}
        return call_user_func_array($this->action, $this->arguments);
    }

}