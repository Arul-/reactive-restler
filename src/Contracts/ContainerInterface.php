<?php namespace Luracast\Restler\Contracts;

use Psr\Container\ContainerInterface as PsrContainer;

interface ContainerInterface extends PsrContainer
{
    public function __construct(array $aliases = [], array $abstractAliases = []);

    public function make($abstract, array $parameters = []);
}