<?php
/**
 * Vanilla 2 Exporter
 *
 * This script exports other forum databases to the Vanilla 2 import format.
 * To use this script, copy it to your web server and open it in your browser.
 * If you have a large database, make the directory writable so that the export file can be saved locally and zipped.
 *
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
define('APPLICATION', 'Porter');
define('APPLICATION_VERSION', '2.3');

error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);

// Make sure a default time zone is set
if (ini_get('date.timezone') == '') {
    date_default_timezone_set('America/Montreal');
}

// Recognize if we're running from cli.
if (PHP_SAPI == 'cli') {
    define('CONSOLE', true);
}

/** @var array Supported forum packages: classname => array(name, prefix, features) */
global $supported;

// Support Files
include_once 'config.php';
include_once 'class.exportmodel.php';
include_once 'class.exportcontroller.php';
include_once 'functions/core-functions.php';
include_once 'functions/render-functions.php';
include_once 'functions/filter-functions.php';
include_once 'functions/commandline-functions.php';
include_once 'functions/structure-functions.php';
include_once 'functions/feature-functions.php';
include_once 'database/class.dbfactory.php';
include_once 'database/interface.dbresource.php';
include_once 'database/class.resultset.php';
include_once 'database/class.mysqlidb.php';
include_once 'database/class.mysqldb.php';
include_once 'database/class.pdodb.php';

// Use error handler in functions.php
set_error_handler("ErrorHandler");

// Set Vanilla to appear first in the list.
$supported = array(
    'vanilla1' => array('name' => 'Vanilla 1', 'prefix' => 'LUM_'),
    'vanilla2' => array('name' => 'Vanilla 2', 'prefix' => 'GDN_')
);

// Include individual software porters.
// MAKESKIPSTART
$paths = glob(dirname(__FILE__) . '/packages/*.php');
foreach ($paths as $path) {
    if (is_readable($path)) {
        include_once $path;
    }
}
// MAKESKIPEND

// If running from cli, execute its command.
if (defined('CONSOLE')) {
    parseCommandLine();
}

// Instantiate the appropriate controller or display the input page.
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
        // Mini-Factory for conducting exports.
        $class = ucwords($_POST['type']);
        $controller = new $class();
        if (!method_exists($controller, $method)) {
            echo "This datasource type does not support {$method}.\n";
            exit();
        }
        $controller->$method();
    } else {
        echo 'Invalid type specified: ' . $_POST['type'];
    }
} else {
    // Show the web UI to start an export.
    $canWrite = testWrite();
    viewForm(array('Supported' => $supported, 'CanWrite' => $canWrite));
}

// Console output should end in newline.
if (defined('CONSOLE')) {
    echo "\n";
}
