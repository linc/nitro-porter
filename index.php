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
$packages = loadManifest();
foreach ($packages as $name) {
    $classname = '\NitroPorter\Package\\' . $name;
    $classname::registerSupport();
}
$supported = \NitroPorter\SupportManager::getInstance()->getSupport();

set_error_handler("ErrorHandler");

if (defined('CONSOLE')) {
    // Execute CLI commends.
    parseCommandLine();
}

// Router.
$method = 'DoExport';
if (isset($_REQUEST['features'])) {
    // Feature list or table.
    $set = (isset($_REQUEST['cloud'])) ? array('core', 'addons', 'cloud') : false;
    $set = vanillaFeatures($set);

    if (isset($_REQUEST['type'])) {
        viewFeatureList($_REQUEST['type'], $set);
    } else {
        viewFeatureTable($set);
    }
} elseif (isset($_POST['type'])) {
    if (array_key_exists($_POST['type'], $supported)) {
        // Factory.
        $class = ucwords($_POST['type']);
        $controller = new $class();
        if (!method_exists($controller, $method)) {
            echo "This datasource type does not support {$method}.\n";
            exit();
        }
        $controller->$method();
    } else {
        echo 'Invalid type specified: ' . htmlspecialchars($_POST['type']);
    }
} else {
    // Web UI.
    $canWrite = testWrite();
    viewForm(array('Supported' => $supported, 'CanWrite' => $canWrite));
}

if (defined('CONSOLE')) {
    // Console output should end in newline.
    echo "\n";
}
