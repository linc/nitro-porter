<?php

// Bootstrap.
define('WEB', true);
require_once './bootstrap.php';

// Web Router.
if (isset($_POST['package'])) {
    $package = \NitroPorter\ExportFactory::build();
    $package->run();
} else {
    \NitroPorter\Render::route();
}
