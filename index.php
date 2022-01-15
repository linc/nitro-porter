<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

// Autoload.
require_once 'vendor/autoload.php';
if (!file_exists(__DIR__ . '/config.php')) {
    die('Required file config.php missing');
}

// Environment.
const ROOT_DIR = __DIR__;
const DB_EXTENSION = 'pdo'; // @todo
error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);
if (ini_get('date.timezone') == '') {
    date_default_timezone_set('America/Detroit');
}
set_error_handler("ErrorHandler");

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
