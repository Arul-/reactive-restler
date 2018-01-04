<?php
use Luracast\Restler\iAuthenticate;
use Luracast\Restler\Reactler;

class SimpleAuth implements iAuthenticate
{
    const KEY = 'rEsTlEr2';
    /**
     * @var Reactler
     */
    public $restler;

    function __isAllowed()
    {
        $query = $this->restler->query();
        return isset($query['key']) && $query['key'] == SimpleAuth::KEY ? TRUE : FALSE;
    }

    public function __getWWWAuthenticateString()
    {
        return 'Query name="key"';
    }

    function key()
    {
        return SimpleAuth::KEY;
    }
}