<?php

if (!defined('WEB') && !defined('CONSOLE')) {
    die('Cannot call bootstrap directly.');
}

// Autoload.
require_once 'vendor/autoload.php';

// Environment.
const ROOT_DIR = __DIR__;
set_error_handler("ErrorHandler");
if (ini_get('date.timezone') == '') {
    date_default_timezone_set('America/Detroit');
}

// Require config.
if (!file_exists(__DIR__ . '/config.php')) {
    die('Required file config.php missing');
}

// Load package manifest.
\Porter\PackageSupport::getInstance()->set(loadManifest());
