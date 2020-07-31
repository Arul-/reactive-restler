<?php


namespace Luracast\Restler\Contracts;


use JsonSerializable;
use Luracast\Restler\Data\Returns;

interface TypedResponseInterface extends JsonSerializable
{
    public function type(): Returns;
}
