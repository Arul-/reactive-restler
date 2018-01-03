<?php namespace Luracast\Restler\MediaTypes;


class Tsv extends Csv
{
    const MIME = 'text/tab-separated-values';
    const EXTENSION = 'tsv';
    public static $delimiter = "\t";
    public static $enclosure = '"';
    public static $escape = '\\';
    public static $haveHeaders = null;
}