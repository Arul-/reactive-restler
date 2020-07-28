<?php


namespace Luracast\Restler\Data;


use Luracast\Restler\Utils\CommentParser;
use ReflectionNamedType;

class Returns extends Type
{
    public static function fromReturnType(?ReflectionNamedType $reflectionType, ?array $metadata, array $scope): self
    {
        $instance = new static();
        $types = $metadata['type'] ?? ['array'];
        $itemTypes = $metadata[CommentParser::$embeddedDataName]['type'] ?? ['object'];
        $instance->description = $metadata['description'] ?? '';
        $instance->apply($reflectionType, $types, $itemTypes, $scope);
        return $instance;
    }
}
