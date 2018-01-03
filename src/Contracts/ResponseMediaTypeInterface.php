<?php namespace Luracast\Restler\Contracts;

interface ResponseMediaTypeInterface extends MediaTypeInterface
{
    public function encode($data, bool $humanReadable = false): string;
}