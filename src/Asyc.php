<?php

namespace Luracast\Restler;


use Exception;
use Generator;

class Asyc
{
    /**
     * @param Generator $flow
     * @param callable $callback
     */
    public static function run(Generator $flow, Callable $callback = null)
    {
        if ($flow->valid()) {
            $value = $flow->current();
            $args = [];
            $func = [];
            if (is_array($value) && count($value) > 1) {
                $func[] = array_shift($value);
                if (is_callable($func[0])) {
                    $func = $func[0];
                    echo '   yield ' . $func . '(' . implode($value, ', ') . ');' . PHP_EOL;
                } else {
                    $func[] = array_shift($value);
                    echo '   yield ' . get_class($func[0]) . '->' . $func[1] . '(' . implode($value,
                            ', ') . ');' . PHP_EOL;
                }
                $args = $value;
            } else {
                $func = $value;
            }
            if (is_callable($func)) {
                $args[] = function ($error, $result) use ($flow, $callback) {
                    if ($error) {
                        throw $error;
                    }
                    $flow->send($result);
                    static::run($flow, $callback);
                };
                $result = call_user_func_array($func, $args);
                /** @var \React\Promise\Promise $promise */
                $promise = null;

                if (is_a($result, 'React\Promise\PromisorInterface')) {
                    $promise = $result->promise();

                } elseif (is_a($result, 'React\Promise\PromiseInterface')) {
                    $promise = $result;
                }
                if ($promise) {
                    $promise->then(function ($result) use ($flow, $callback) {
                        $flow->send($result);
                        static::run($flow, $callback);
                    }, function ($error) {
                        throw new Exception($error);
                    });
                }
            } elseif ($value instanceof Generator) {
                static::run($value, function ($value) use ($flow, $callback) {
                    $flow->send($value);
                    static::run($flow, $callback);
                });
            } else {
                $flow->send($value);
                static::run($flow);
            }
        } elseif (is_callable($callback)) {
            $callback($flow->getReturn());
        }
    }

}