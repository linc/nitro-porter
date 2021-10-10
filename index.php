<?php
/**
 * Vanilla 2 Exporter
 *
 * This script exports other forum databases to the Vanilla 2 import format.
 * To use this script, copy it to your web server and open it in your browser.
 * If you have a large database, make the directory writable so that the export file can be saved locally and zipped.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */
define('APPLICATION_VERSION', '2.5');

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

// Autoloader.
require_once 'vendor/autoload.php';

$packages = [
    'AdvancedForum',
    'AnswerHub',
    'AspPlayground',
    'BbPress1',
    'BbPress2',
    'CodoForum',
    'Drupal6',
    'Drupal7',
    'EsoTalk',
    'ExpressionEngine',
    'FluxBb',
    'FuseTalk',
    'IpBoard3',
    'JForum',
    'Kunena',
    'Mbox',
    'ModxDiscuss',
    'Mvc',
    'MyBb',
    'NodeBb',
    'PhpBb2',
    'PhpBb3',
    'PunBb',
    'Q2a',
    'SimplePress',
    'Smf1',
    'Smf2',
    'Toast',
    'UserVoice',
    'Vanilla1',
    'Vanilla2',
    'VBulletin',
    'VBulletin5',
    'WebWiz',
    'Xenforo',
    'Yaf',
];

foreach ($packages as $name) {
    $classname = '\NitroPorter\Package\\'.$name;
    $classname::registerSupport();
}

$supported = \NitroPorter\SupportManager::getInstance()->getSupport();

// Select database driver.
define('DB_EXTENSION', 'pdo');

// Use error handler in functions.php
set_error_handler("ErrorHandler");
if(!defined('DB_EXTENSION')) {
    die('There is an error in your config. You need to set your database extension properly.');
}

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
        echo 'Invalid type specified: ' . htmlspecialchars($_POST['type']);
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
