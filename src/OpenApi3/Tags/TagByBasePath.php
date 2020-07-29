<?php


namespace Luracast\Restler\OpenApi3\Tags;


use Luracast\Restler\Data\Route;
use Luracast\Restler\Utils\Text;

class TagByBasePath implements Tagger
{
    public static $descriptions = [
        'root' => 'main api'
    ];

    /**
     * @param Route $route
     * @return string[] in tag => description format
     */
    public static function tags(Route $route): array
    {
        $base = strtok($route->url, '/');
        if (empty($base)) {
            $base = 'root';
        } else {
            $base = Text::title($base);
        }

        return [$base => self::$descriptions[$base] ?? ''];
    }
}
