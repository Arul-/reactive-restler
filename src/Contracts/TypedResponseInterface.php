<?php


namespace Luracast\Restler\Contracts;


use JsonSerializable;
use Luracast\Restler\Data\Returns;

interface TypedResponseInterface extends JsonSerializable
{
    public static function responds(string  ...$types): Returns;
}
