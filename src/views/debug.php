<?php

use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Restler;
use Luracast\Restler\Utils\Dump;
use Psr\Http\Message\RequestInterface;

$data['render'] = $render = function ($data, $shadow = true) use (&$render) {
    $r = '';
    if (empty($data)) {
        return $r;
    }
    $r .= $shadow ? "<ul class=\"shadow\">\n" : "<ul>\n";
    if (is_iterable($data)) {
        // field name
        foreach ($data as $key => $value) {
            $r .= '<li>';
            $r .= is_numeric($key)
                ? "<strong>[$key]</strong> "
                : "<strong>$key: </strong>";
            $r .= '<span>';
            if (is_iterable($value)) {
                // recursive
                $r .= $render($value, false);
            } else {
                // value, with hyperlinked hyperlinks
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                $value = htmlentities($value, ENT_COMPAT, 'UTF-8');
                if (strpos($value, 'http://') === 0) {
                    $r .= '<a href="' . $value . '">' . $value . '</a>';
                } else {
                    $r .= $value;
                }
            }
            $r .= "</span></li>\n";
        }
    } elseif (is_bool($data)) {
        $r .= '<li>' . ($data ? 'true' : 'false') . '</li>';
    } else {
        $r .= "<li><strong>$data</strong></li>";
    }
    $r .= "</ul>\n";
    return $r;
};

$icon = '';
if ($success && isset($api)) {
    $arguments = implode(', ', $api->parameters);
    $icon = "<icon class=\"success\"></icon>";
    $title = "{$api->className}::"
        . "{$api->methodName}({$arguments})";
} else {
    if (isset($response['error']['message'])) {
        $icon = '<icon class="denied"></icon>';
        $title = end(explode(':', $response['error']['message'], 2));
    } else {
        $icon = '<icon class="warning"></icon>';
        $title = 'No Matching Resource';
    }
}
$template_vars = $data;
unset($template_vars['response']);
unset($template_vars['api']);
unset($template_vars['request']);
unset($template_vars['restler']);

$requestHeaders = Dump::requestHeaders($container->get(RequestInterface::class));
$responseHeaders = 'HTTP/1.1 ' . $restler->responseCode . ' ' . HttpException::$codes[$restler->responseCode] . PHP_EOL;
foreach ($restler->responseHeaders as $k => $v) {
    $responseHeaders .= "$k: $v\r\n";
}
$version = Restler::VERSION;
return <<<TEMPLATE
<html>
    <head>
        <title>$title</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <style>
            {$_('read', 'debug.css')}
        </style>
    </head>
    <body>
        <div id="breadcrumbs-one">
        </div>
        <header>
            <h1>$title</h1>
        </header>
        <article>
            <h2>Request:</h2>
            <pre class="header">$requestHeaders</pre>
        
            <h2>Response:
                <right>$icon</right>
            </h2>
            <pre class="header">$responseHeaders</pre>
            {$_('render', $response)}
            <h2>Additional Template Data:</h2>
            {$_('render', $template_vars)}
            <p>Restler v{$version}</p>
        </article>
    </body>
</html>
TEMPLATE;

