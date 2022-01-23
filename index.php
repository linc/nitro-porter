<?php

// Bootstrap.
require_once './bootstrap.php';

// Parse the web request.
$input = \Porter\Request::instance()->parseWeb();
\Porter\Request::instance()->load($input);

// Router.
$command = \Porter\Router::run(\Porter\Request::instance());
call_user_func($command, \Porter\Request::instance());
