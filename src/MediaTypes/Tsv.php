<?php
namespace Luracast\Restler\MediaTypes;


class Tsv extends Csv
{
    public const MIME = 'text/tab-separated-values';
    public const EXTENSION = 'tsv';
    public static $delimiter = "\t";
    public static $enclosure = '"';
    public static $escape = '\\';
    public static $haveHeaders = null;
}
