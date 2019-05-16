<?php


namespace Luracast\Restler\Utils;


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
     * @var array[ValidationInfo]
     */
    public $parameters = [];

    /**
     * @var int access level
     */
    public $access = self::PUBLIC;

    public $requestMediaTypes = ['application/json'];
    public $responseMediaTypes = ['application/json'];

    /**
     * @var array
     */
    private $arguments = [];

    public function addParameter(ValidationInfo $parameter)
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
        $this->arguments = $p;
        return $p;
    }

    public function body()
    {
        return array_filter($this->parameters, function ($v) {
            return $v->from === 'body';
        });
    }

    public function call(array $arguments)
    {
        $p = $this->apply($arguments);
        return call_user_func_array($this->action, $p);
    }

}