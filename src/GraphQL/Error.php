<?php


namespace Luracast\Restler\GraphQL;


use Exception;
use GraphQL\Error\ClientAware;
use Throwable;

class Error extends Exception implements ClientAware
{
    /**
     * @var string
     */
    private $category;

    public function __construct(string $category, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->category = $category;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     * @return bool
     *
     * @api
     */
    public function isClientSafe()
    {
        return true;
    }

    /**
     * Returns string describing a category of the error.
     *
     * Value "graphql" is reserved for errors produced by query parsing or validation, do not use it.
     *
     * @return string
     *
     * @api
     */
    public function getCategory()
    {
        return $this->category;
    }
}
