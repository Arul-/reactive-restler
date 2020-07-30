<?php

/**
 * that walk you through restler examples.
 */
class Say
{
    function hello($to = 'world')
    {
        return "Hello $to!";
    }

    function hi($to)
    {
        return "Hi $to!";
    }
}
