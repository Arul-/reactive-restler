<?php


namespace Luracast\Restler\Contracts;


use Luracast\Restler\Data\Param;

interface TypedRequestInterface extends ValueObjectInterface
{
    public function type(): Param;
}
