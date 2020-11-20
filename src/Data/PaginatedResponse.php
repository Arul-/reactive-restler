<?php


namespace Luracast\Restler\Data;


use JsonSerializable;
use Luracast\Restler\Contracts\GenericResponseInterface;
use ReflectionClass;

class PaginatedResponse implements GenericResponseInterface
{
    /**
     * @var JsonSerializable
     */
    private $serializable;

    public function __construct(JsonSerializable $serializable)
    {
        $this->serializable = $serializable;
    }

    public static function responds(string ...$types): Returns
    {
        $data = empty($types)
            ? Returns::__set_state(['type' => 'object', 'scalar' => false])
            : Returns::fromClass(new ReflectionClass($types[0]));
        $data->multiple = true;
        $data->nullable = false;
        return Returns::__set_state([
            'type' => 'PaginatedResponse',
            'properties' => [
                'total' => Returns::__set_state(['type' => 'int']),
                'per_page' => Returns::__set_state(['type' => 'int']),
                'current_page' => Returns::__set_state(['type' => 'int']),
                'last_page' => Returns::__set_state(['type' => 'int']),
                'first_page_url' => Returns::__set_state(['type' => 'string']),
                'last_page_url' => Returns::__set_state(['type' => 'string']),
                'next_page_url' => Returns::__set_state(['type' => 'string']),
                'prev_page_url' => Returns::__set_state(['type' => 'string']),
                'path' => Returns::__set_state(['type' => 'string']),
                'from' => Returns::__set_state(['type' => 'int']),
                'to' => Returns::__set_state(['type' => 'int']),
                'data' => $data,
            ]
        ]);
    }

    public function jsonSerialize()
    {
        return $this->serializable->jsonSerialize();
    }
}
