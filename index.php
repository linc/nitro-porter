<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

// Environment.
const VERSION = '2.5';
const ROOT_DIR = __DIR__;
const DB_EXTENSION = 'pdo'; // @todo

if (PHP_SAPI == 'cli') {
    // Running from CLI.
    define('CONSOLE', true);
}

if (!file_exists(ROOT_DIR . '/config.php')) {
    // Require config.
    die('Required file config.php missing');
}

error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);
if (ini_get('date.timezone') == '') {
    // Set default time zone.
    date_default_timezone_set('America/Detroit');
}

// Autoload.
require_once 'vendor/autoload.php';

// Bootstrap.
set_error_handler("ErrorHandler");

if (defined('CONSOLE')) {
    // Execute CLI commends.
    parseCommandLine();
}

// Web Router.
if (isset($_REQUEST['features'])) {
    if (isset($_REQUEST['type'])) {
        // Single package feature list.
        viewFeatureList($_REQUEST['type'], vanillaFeatures());
    } else {
        // Overview table.
        viewFeatureTable();
    }
} elseif (isset($_POST['type'])) {
    dispatch($_POST['type']);
} else {
    viewForm(); // Starting Web UI.
}

if (defined('CONSOLE')) {
    // Console output should end in newline.
    echo "\n";
}
