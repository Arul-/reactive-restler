<?php namespace Luracast\Restler;


use Illuminate\Container\Container as LaravelContainer;
use Psr\Container\ContainerInterface;

class Container extends LaravelContainer implements ContainerInterface
{
    public function has($id)
    {
        return parent::has($id);
    }

    public function get($id)
    {
        return parent::get($id);
    }

}