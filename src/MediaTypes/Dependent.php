<?php namespace Luracast\Restler\MediaTypes;

use Luracast\Restler\Contracts\DependentTrait;
use Luracast\Restler\Utils\Convert;

abstract class Dependent extends MediaType
{
    use DependentTrait;

    public function __construct(Convert $convert)
    {
        parent::__construct($convert);
        static::checkDependencies();
    }
}
