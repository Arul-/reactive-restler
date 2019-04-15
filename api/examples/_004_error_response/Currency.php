<?php

use Luracast\Restler\Exceptions\HttpException;

class Currency
{
    function format($number = null)
    {
        /**
         * There is a better way to validate in Restler 3
         * Here we manually validate to show the use of exceptions
         */
        if (is_null($number)) {
            throw new HttpException(400);
        }
        if (!is_numeric($number)) {
            throw new HttpException(400, 'not a valid number');
        }

        // let's print the international format for the en_US locale
        setlocale(LC_MONETARY, 'en_US.UTF-8');
        return money_format('%i', $number);
    }
}
