<?php namespace Luracast\Restler\MediaTypes;


use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\HttpException;

class Upload extends MediaType implements RequestMediaTypeInterface
{
    const MIME = 'multipart/form-data';
    const EXTENSION = 'post';

    public function decode(string $data)
    {
        // TODO: Implement decode() method.
        throw new HttpException(501);
    }
}