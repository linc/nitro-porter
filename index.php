<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

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

// Load the support manifest.
\NitroPorter\SupportManager::getInstance()->setSupport(loadManifest());

// CLI Router.
if (PHP_SAPI == 'cli') {
    define('CONSOLE', true);
    $cli = new \NitroPorter\CommandLine();
    $cli->parseCommandLine();
}

// Web Router.
if (isset($_POST['type'])) {
    $controller = getValidPackage($_POST['type']);
    $controller->doExport();
} else {
    \NitroPorter\Render::route();
}

if (defined('CONSOLE')) {
    // Console output should end in newline.
    echo "\n";
}
