<?php

// Bootstrap.
define('WEB', true);
require_once './bootstrap.php';

// Parse the web request.
$input = \Porter\Request::instance()->parseWeb();
\Porter\Request::instance()->load($input);

// Web Router.
if (\Porter\Request::instance()->get('package')) {
    $package = \Porter\ExportFactory::build();
    $package->run();
} else {
    \Porter\Render::route();
}
