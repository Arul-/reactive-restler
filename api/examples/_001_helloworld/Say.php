<?php

/**
 * that walk you through restler examples.
 */
class Say
{
    function hello(string $to = 'world'): string
    {
        return "Hello $to!";
    }

    function hi(string $to): string
    {
        return "Hi $to!";
    }
}
