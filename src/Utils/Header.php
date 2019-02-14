<?php namespace Luracast\Restler\Utils;


class Header
{

    /**
     * Pass any content negotiation header such as Accept,
     * Accept-Language to break it up and sort the resulting array by
     * the order of negotiation.
     *
     * @static
     *
     * @param string $accept header value
     *
     * @return array sorted by the priority
     */
    public static function sortByPriority($accept)
    {
        $acceptList = array();
        $accepts = explode(',', strtolower($accept));
        if (!is_array($accepts)) {
            $accepts = array($accepts);
        }
        foreach ($accepts as $pos => $accept) {
            $parts = explode(';q=', trim($accept));
            $type = strtok(array_shift($parts), ';');
            $quality = count($parts) ?
                floatval(array_shift($parts)) :
                (1000 - $pos) / 1000;
            $acceptList[$type] = $quality;
        }
        arsort($acceptList);
        return $acceptList;
    }
}