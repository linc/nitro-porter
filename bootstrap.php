<?php

// Autoload.
require_once 'vendor/autoload.php';

// Environment.
const ROOT_DIR = __DIR__;
set_error_handler("ErrorHandler");
if (ini_get('date.timezone') == '') {
    date_default_timezone_set('America/Detroit');
}

// Require & load config.
if (!file_exists(__DIR__ . '/config.php')) {
    die('Required file config.php missing');
}
\Porter\Config::getInstance()->set(loadConfig());

// Load package manifest.
\Porter\Support::getInstance()->set(loadManifest());
