<?php namespace Luracast\Restler;


class ExplorerInfo
{
    public static $title = 'Restler API Explorer';
    public static $description = 'Example api documentation brought to you by **restler team**';
    public static $termsOfServiceUrl = null;
    public static $contactName = 'Restler Support';
    public static $contactEmail = 'arul@luracast.com';
    public static $contactUrl = 'https://luracast.com/products/restler';
    public static $license = 'LGPL-2.1';
    public static $licenseUrl = 'https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html';

    public static function format($swaggerVersion)
    {
        $swaggerVersion = (int)$swaggerVersion;
        switch ($swaggerVersion) {
            case 1:
                return [
                    'title' => static::$title,
                    'description' => static::$description,
                    'termsOfServiceUrl' => static::$termsOfServiceUrl,
                    'contact' => static::$contactEmail,
                    'license' => static::$license,
                    'licenseUrl' => static::$licenseUrl,
                ];
            case 2:
            case 3:
                return [
                    'title' => static::$title,
                    'description' => static::$description,
                    'termsOfService' => static::$termsOfServiceUrl,
                    'contact' => [
                        'name' => static::$contactName,
                        'email' => static::$contactEmail,
                        'url' => static::$contactUrl,
                    ],
                    'license' => [
                        'name' => static::$license,
                        'url' => static::$licenseUrl,
                    ],
                ];
        }
        return [];
    }
}