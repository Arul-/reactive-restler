<?php namespace Luracast\Restler\Contracts;

use Psr\Container\ContainerInterface as PsrContainer;

interface ContainerInterface extends PsrContainer
{
    public function __construct(array $aliases = [], array $abstractAliases = []);

    public function setAliases(array $aliases, bool $clear = false);

    public function setAbstractAliases(array $abstractAliases, bool $clear = false);

    public function setConfig(&$config);

    public function make($abstract, array $parameters = []);

    public function instance($abstract, $instance);
}