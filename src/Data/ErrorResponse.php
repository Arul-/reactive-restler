<?php


namespace Luracast\Restler\Data;


use Exception;
use Luracast\Restler\Contracts\TypedResponseInterface;
use Luracast\Restler\Exceptions\HttpException;

class ErrorResponse implements TypedResponseInterface
{
    /** @var Error */
    public $error;
    /** @var Debug */
    public $debug;
    /** @var array {@type associative} */
    private $details = [];

    public function __construct(Exception $exception, bool $debug = false)
    {
        $this->error = new Error($exception->getCode(), $exception->getErrorMessage());
        if ($exception instanceof HttpException) {
            $this->details = $exception->getDetails();
        }
        if ($debug) {
            $innerException = $exception;
            while ($prev = $innerException->getPrevious()) {
                $innerException = $prev;
            }
            $trace = array_slice($innerException->getTrace(), 0, 10);
            $this->debug = new Debug($exception->getSource(), array_map([static::class, 'simplifyTrace'], $trace));
        }
    }


    public static function simplifyTrace(array $trace): array
    {
        $parts = explode('\\', $trace['class'] ?? '');
        $class = array_pop($parts);
        $parts = explode('/', $trace['file'] ?? '');
        return [
            'file' => array_pop($parts) . (isset($trace['line']) ? ':' . $trace['line'] : ''),
            'function' => $class . ($trace['type'] ?? '') . $trace['function'],
            'args' => array_map([static::class, 'simplifyTraceArgs'], $trace['args'] ?? []),
        ];
    }

    public static function simplifyTraceArgs($argument)
    {
        if (is_object($argument)) {
            return 'new ' . get_class($argument) . '()';
        }
        if (is_array($argument)) {
            return array_map(__METHOD__, $argument);
        }
        if (is_resource($argument)) {
            return 'resource(' . get_resource_type($argument) . ')';
        }
        return $argument;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return array_filter(get_object_vars($this));
    }

    public static function responds(string ...$types): Returns
    {
        return Returns::__set_state([
            'type' => 'ErrorResponse',
            'scalar' => false,
            'description' => '',
            'properties' =>
                [
                    'error' =>
                        Returns::__set_state([
                            'type' => 'Error',
                            'scalar' => false,
                            'description' => '',
                            'properties' =>
                                [
                                    'code' =>
                                        Returns::__set_state([
                                            'type' => 'int',
                                            'description' => '',
                                        ]),
                                    'message' =>
                                        Returns::__set_state([
                                            'type' => 'string',
                                            'description' => '',
                                        ]),
                                ],
                        ]),
                    'debug' =>
                        Returns::__set_state([
                            'type' => 'Debug',
                            'scalar' => false,
                            'description' => '',
                            'properties' =>
                                [
                                    'source' =>
                                        Returns::__set_state([
                                            'type' => 'string',
                                            'description' => '',
                                        ]),
                                    'trace' =>
                                        Returns::__set_state([
                                            'type' => 'Trace',
                                            'scalar' => false,
                                            'description' => '',
                                            'properties' =>
                                                [
                                                    'file' =>
                                                        Returns::__set_state([
                                                            'type' => 'string',
                                                            'multiple' => false,
                                                            'nullable' => false,
                                                            'scalar' => true,
                                                            'format' => null,
                                                            'properties' => null,
                                                            'description' => '',
                                                            'reference' => null,
                                                        ]),
                                                    'function' =>
                                                        Returns::__set_state([
                                                            'type' => 'string',
                                                            'multiple' => false,
                                                            'nullable' => false,
                                                            'scalar' => true,
                                                            'format' => null,
                                                            'properties' => null,
                                                            'description' => '',
                                                            'reference' => null,
                                                        ]),
                                                    'args' =>
                                                        Returns::__set_state([
                                                            'type' => 'string',
                                                            'multiple' => true,
                                                            'nullable' => false,
                                                            'scalar' => true,
                                                            'format' => null,
                                                            'properties' => null,
                                                            'description' => '',
                                                            'reference' => null,
                                                        ]),
                                                ],
                                        ]),
                                ],


                        ]),
                ],
        ]);
    }
}

class Error
{
    /** @var int */
    public $code;
    /** @var string */
    public $message;

    public function __construct(int $code, string $message)
    {
        $this->code = $code;
        $this->message = $message;
    }
}

class Debug
{
    /** @var string */
    public $source;
    /** @var Trace[] */
    public $trace = [];

    public function __construct(string $source, array $trace)
    {
        $this->source = $source;
        $this->trace = $trace;
    }
}

class Trace
{
    /** @var string */
    public $file;
    /** @var string */
    public $function;
    /** @var string[] */
    public $args = [];

    public function __construct(string $file, string $function, array $args)
    {

        $this->file = $file;
        $this->function = $function;
        $this->args = $args;
    }
}
