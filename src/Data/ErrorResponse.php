<?php


namespace Luracast\Restler\Data;


use Exception;
use Luracast\Restler\Contracts\GenericResponseInterface;
use Luracast\Restler\Exceptions\HttpException;

class ErrorResponse implements GenericResponseInterface
{
    public $response = [];

    public function __construct(Exception $exception, bool $debug = false)
    {
        $this->response['error'] = [
            'code' => $exception->getCode(),
            'message' => $exception->getErrorMessage()
        ];
        if ($exception instanceof HttpException) {
            $this->response += $exception->getDetails();
        }
        if ($debug) {
            $innerException = $exception;
            while ($prev = $innerException->getPrevious()) {
                $innerException = $prev;
            }
            $trace = array_slice($innerException->getTrace(), 0, 10);
            $this->response['debug'] = [
                'source' => $exception->getSource(),
                'trace' => array_map([static::class, 'simplifyTrace'], $trace)
            ];
        }
    }

    public function jsonSerialize()
    {
        return $this->response;
    }

    public static function responds(string ...$types): Returns
    {
        return Returns::__set_state([
            'type' => 'ErrorResponse',
            'scalar' => false,
            'description' => '',
            'properties' =>
                [
                    'error' => Returns::__set_state([
                        'type' => 'Error',
                        'scalar' => false,
                        'properties' =>
                            [
                                'code' => Returns::__set_state(['type' => 'int']),
                                'message' => Returns::__set_state(['type' => 'string']),
                            ],
                    ]),
                    'debug' => Returns::__set_state([
                        'type' => 'Debug',
                        'scalar' => false,
                        'description' => '',
                        'properties' =>
                            [
                                'source' => Returns::__set_state(['type' => 'string']),
                                'trace' => Returns::__set_state([
                                    'type' => 'Trace',
                                    'scalar' => false,
                                    'properties' =>
                                        [
                                            'file' => Returns::__set_state([
                                                'type' => 'string',
                                                'multiple' => false,
                                                'nullable' => false,
                                                'scalar' => true
                                            ]),
                                            'function' => Returns::__set_state([
                                                'type' => 'string',
                                                'multiple' => false,
                                                'nullable' => false,
                                                'scalar' => true
                                            ]),
                                            'args' => Returns::__set_state([
                                                'type' => 'string',
                                                'multiple' => true,
                                                'nullable' => false,
                                                'scalar' => true
                                            ]),
                                        ],
                                ]),
                            ],


                    ]),
                ],
        ]);
    }

    private static function simplifyTrace(array $trace): array
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

    private static function simplifyTraceArgs($argument)
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
}

