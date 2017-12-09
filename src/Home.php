<?php


class Home
{
    function get()
    {
        return ['success' => true];
    }

    /**
     * @param int $id
     * @return array
     */
    function kitchen($id)
    {
        return compact('id');
    }

    /**
     * @param bool $open
     * @return array
     */
    function bedroom($open = false)
    {
        return compact('open');
    }

    /**
     * @param array $param {@from body}
     * @return array
     */
    function post(array $param)
    {
        return compact('param');
    }
}