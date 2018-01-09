<?php

use Luracast\Restler\Compose;
use Luracast\Restler\Contracts\{
    ComposerInterface, RequestMediaTypeInterface, ResponseMediaTypeInterface, FilterInterface, AuthenticationInterface
};
use Luracast\Restler\Data\iValidate;
use Luracast\Restler\Filters\RateLimiter;
use Luracast\Restler\HumanReadableCache;
use Luracast\Restler\iCache;
use Luracast\Restler\iCompose;
use Luracast\Restler\iIdentifyUser;
use Luracast\Restler\MediaTypes\{
    Amf, Csv, Js, Json, Plist, Tsv, Upload, UrlEncoded, Xml, Yaml
};
use Luracast\Restler\Reactler;
use Luracast\Restler\User;
use Luracast\Restler\Utils\Validator;
use Psr\Http\Message\{
    ResponseInterface, ServerRequestInterface
};
use Psr\SimpleCache\CacheInterface;
use React\Http\{
    ServerRequest, Response
};

return [
    /*
    |--------------------------------------------------------------------------
    | Implementations
    |--------------------------------------------------------------------------
    |
    | This array of interfaces and implementing classes that can by default
    |
    */
    'implementations' => [
        iCache::class => HumanReadableCache::class,
        iCompose::class => Compose::class,
        iValidate::class => Validator::class,
        iIdentifyUser::class => User::class,
        RequestMediaTypeInterface::class => Json::class,
        ResponseMediaTypeInterface::class => Json::class,
        ServerRequestInterface::class => ServerRequest::class,
        ResponseInterface::class => Response::class,
        FilterInterface::class => RateLimiter::class,
        AuthenticationInterface::class => SimpleAuth::class
    ],
    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */
    'aliases' => [
        // Core
        'Application' => Reactler::class,
        // Formats
        'Amf' => Amf::class,
        'Csv' => Csv::class,
        'Js' => Js::class,
        'Json' => Json::class,
        'Plist' => Plist::class,
        'Tsv' => Tsv::class,
        'Upload' => Upload::class,
        'UrlEncoded' => UrlEncoded::class,
        'Xml' => Xml::class,
        'Yaml' => Yaml::class,
        // Exception
        'HttpException' => HttpException::class,
        // Backward Compatibility
        'RestException' => HttpException::class,
        'Restler' => Reactler::class,
        'JsonFormat' => Json::class,
        'JsFormat' => Js::class,
        'XmlFormat' => Xml::class,
    ]
];
