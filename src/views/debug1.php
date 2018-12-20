<?php

use Luracast\Restler\Contracts\ContainerInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Restler;
use Luracast\Restler\Utils\Dump;
use Psr\Http\Message\RequestInterface;

/** @var Restler $restler */
$restler = $data->restler;
/** @var ContainerInterface $container */
$container = $data->container;

$template_vars = $data;//get_defined_vars();

$call_trace = '';


function exceptions(Restler $restler)
{
    global $call_trace;
    $source = $restler->exception;
    if ($source) {
        $traces = array();
        do {
            $traces += $source->getTrace();
        } while ($source = $source->getPrevious());
        $traces += debug_backtrace();
        $call_trace = parse_backtrace($traces, 0);
    } else {
        $call_trace = parse_backtrace(debug_backtrace());
    }
}

//exceptions($restler);

function parse_backtrace($raw, $skip = 1)
{
    $output = "";
    foreach ($raw as $entry) {
        if ($skip-- > 0) {
            continue;
        }
        //$output .= print_r($entry, true) . "\n";
        $output .= "\nFile: " . $entry['file'] . " (Line: " . $entry['line'] . ")\n";
        if (isset($entry['class'])) {
            $output .= $entry['class'] . "::";
        }
        $output .= $entry['function']
            . "( " . json_encode($entry['args']) . " )\n";
    }
    return $output;
}

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
function render($data, $shadow = true)
{
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
            if (is_array($value)) {
                // recursive
                $r .= render($value, false);
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
}

/*
if ($restler->exception) {
    $stages = $restler->exception->getStages();
    $curStage = $restler->exception->getStage();
    foreach ($stages['success'] as $stage) {
        echo "<a href=\"#\">$stage</a>";
    }
    foreach ($stages['failure'] as $stage) {
        echo '<a href="#" class="failure">'
            . $stage
            . ($stage == $curStage ? ' <span class="state"/> ' : '')
            . '</a>';
    }
} else {
    foreach ($restler->_events as $stage) {
        echo "<a href=\"#\">$stage</a>";
    }
}
*/
$requestHeaders = Dump::requestHeaders($container->get(RequestInterface::class));
$responseHeaders = 'HTTP/1.1 ' . $restler->responseCode . ' ' . HttpException::$codes[$restler->responseCode] . PHP_EOL;
foreach ($restler->responseHeaders as $k => $v) {
    $responseHeaders .= "$k: $v\r\n";
}
return <<<TEMPLATE
<html>
    <head>
        <title>$title</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <style>
            {$_('include','debug.css')}
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
            {render($response)}
            <h2>Additional Template Data:</h2>
            {render($template_vars)}
            <p>Restler v{$restler->VERSION}</p>
        </article>
    </body>
</html>
TEMPLATE;