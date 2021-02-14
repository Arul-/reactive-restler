<?php


class Functions
{
    function server()
    {
        return request()->getServerParams();
    }

    function query()
    {
        return request()->getQueryParams();
    }

    function base_path($path = '')
    {
        return base_path($path);
    }

    function redirect($path = '')
    {
        return redirect($path);
    }
}
