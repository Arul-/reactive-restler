<?php return <<<TEMPLATE
<html>
    <head>
        <title>$title</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <style>
            
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

