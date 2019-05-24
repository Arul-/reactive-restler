<?php


namespace Luracast\Restler\Data;


use Luracast\Restler\Utils\CommentParser;

class Returns extends Type
{

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
        return $instance;
    }

}