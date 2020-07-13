<?php


namespace Luracast\Restler\Data;


use Luracast\Restler\Utils\CommentParser;
use ReflectionNamedType;

class Returns extends Type
{
    public static function fromReturnType(?ReflectionNamedType $type, ?array $metadata, array $scope): self
    {
        $instance = new static();
        $types = $metadata['type'] ?? ['array'];
        $itemTypes = $metadata[CommentParser::$embeddedDataName]['type'] ?? ['string'];
        $instance->description = $metadata['description'] ?? '';
        $instance->apply($type, $types, $itemTypes, $scope);
        return $instance;
    }

    public static function parse(array $properties): self
    {
        $p2 = $properties[CommentParser::$embeddedDataName] ?? [];
        unset($properties[CommentParser::$embeddedDataName]);
        if (isset($p2['type'])) {
            $p2['contentType'] = $p2['type'];
            unset($p2['type']);
        }

        $instance = new static();
        $instance->applyProperties($properties);
        $instance->applyProperties($p2);
        if (is_array($instance->children)) {
            foreach ($instance->children as $key => $child) {
                if (!$child instanceof static) {
                    $instance->children[$key] = static::parse($child);
                }
            }
        }
        $instance->updateFlags();
        return $instance;
    }

}
