<?php


namespace Luracast\Restler\Contracts;


use Luracast\Restler\Data\Param;

interface TypedRequestInterface extends ValueObjectInterface
{
    public static function requests(string  ...$types): Param;
}
