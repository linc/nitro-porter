<?php
/**
 * Vanilla 2 Exporter
 * 
 * This script exports other forum databases to the Vanilla 2 import format.
 * To use this script, copy it to your web server and open it in your browser.
 * If you have a large database, make the directory writable so that the export file can be saved locally and zipped.
 *
 * @copyright 2010-2014 Vanilla Forums Inc.
 * @license GNU GPLv2
 * @package VanillaPorter
 */
define('APPLICATION', 'Porter');
define('APPLICATION_VERSION', '2.0.5');

error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);

// Make sure a default time zone is set
if (ini_get('date.timezone') == '') {
   date_default_timezone_set('America/Montreal');
}

// Recognize if we're running from cli.
if (PHP_SAPI == 'cli') {
   define('CONSOLE', TRUE);
}

/** @var array Supported forum packages: classname => array(name, prefix) */
global $Supported;

// Support Files
include_once 'class.exportmodel.php';
include_once 'class.exportcontroller.php';
include_once 'functions.php';
include_once 'functions.render.php';
include_once 'functions.filter.php';
include_once 'functions.commandline.php';
include_once 'functions.structure.php';

// Use error handler in functions.php
set_error_handler("ErrorHandler");

// Set Vanilla to appear first in the list.
$Supported = array(
   'vanilla1' => array('name'=> 'Vanilla 1.*', 'prefix'=>'LUM_'),
   'vanilla2' => array('name'=> 'Vanilla 2.*', 'prefix'=>'GDN_')
);

// Include individual software porters.
// MAKESKIPSTART
$Paths = glob(dirname(__FILE__).'/packages/class.*.php');
foreach ($Paths as $Path) {
   include_once $Path;
}
// MAKESKIPEND

// If running from cli, execute its command.
if (defined('CONSOLE')) {
   ParseCommandLine();
}

// Instantiate the appropriate controller or display the input page.
$Method = 'DoExport';
if(isset($_POST['type']) && array_key_exists($_POST['type'], $Supported)) {
   // Mini-Factory
   $class = ucwords($_POST['type']);
   $Controller = new $class();
   if (!method_exists($Controller, $Method)) {
      echo "This datasource type does not support {$Method}.\n";
      exit();
   }
   $Controller->$Method();
}
else {
   $CanWrite = TestWrite();
   ViewForm(array('Supported' => $Supported, 'CanWrite' => $CanWrite));
}

// Console output should end in newline.
if (defined('CONSOLE'))
   echo "\n";
