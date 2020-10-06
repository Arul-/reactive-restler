<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <base href="{{ $resourcePathNormalizer }}"/>
    <title>{{ $title }}</title>

    <!-- Including the jQuery UI Human Theme -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.min.css"
          type="text/css" media="all"/>

    <!-- Our own stylesheet -->
    <link rel="stylesheet" type="text/css" href="styles.css"/>

</head>

<body>

<h1>{{ $title }}</h1>

<h2>
    <a href="../">Go
        Back to the Examples &raquo;</a></h2>

<div id="main">

    <ul class="todoList">
        @foreach ($response as $res)
            @include('todo.list', $res->jsonSerialize())
        @endforeach
    </ul>

    <a id="addButton" class="green-button" href="#">Add a Task</a>

</div>

<!-- This div is used as the base for the confirmation jQuery UI POPUP. Hidden by CSS. -->
<div id="dialog-confirm" title="Delete TODO Item?">Are you sure you want to
    delete this task?
</div>

<p class="note">{{ $description }}</p>

<!-- Including our scripts -->

<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script type="text/javascript" src="script.js"></script>

</body>
</html>
