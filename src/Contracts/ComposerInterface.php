<?php namespace Luracast\Restler\Contracts;


use Luracast\Restler\HttpException;

interface ComposerInterface
{
    /**
     * Result of an api call is passed to this method
     * to create a standard structure for the data
     *
     * @param mixed $result can be a primitive or array or object
     */
    public function response($result);

    /**
     * When the api call results in RestException this method
     * will be called to return the error message
     *
     * @param HttpException $exception exception that has reasons for failure
     *
     * @return
     */
    public function message(HttpException $exception);
}