<?php

use Luracast\Restler\iAuthenticate;
use Luracast\Restler\Reactler;

class KeyAuth implements iAuthenticate
{
    /**
     * @var Reactler
     */
    public $restler;

    public function __isAllowed()
    {
        $query = $this->restler->query();
        return isset($query['api_key']) && $query['api_key'] == 'r3rocks';
    }

    public function __getWWWAuthenticateString()
    {
        return 'Query name="api_key"';
    }
}
