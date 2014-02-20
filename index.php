<?php
/**
 * Vanilla 2 Exporter
 * 
 * This script exports other forum databases to the Vanilla 2 import format.
 * To use this script, copy it to your web server and open it in your browser.
 * If you have a large database, make the directory writable so that the export file can be saved locally and zipped.
 *
 * @copyright 2010 Vanilla Forums Inc.
 * @license GNU GPLv2
 * @package VanillaPorter
 */
define('APPLICATION', 'Porter');
define('APPLICATION_VERSION', '1.9.1');

if(TRUE || defined('DEBUG'))
   error_reporting(E_ALL);
else
   error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);

/** @var array Supported forum packages: classname => array(name, prefix) */
global $Supported;

// Support Files
include_once 'class.exportmodel.php';
include_once 'views.php';
include_once 'class.exportcontroller.php';
include_once 'functions.php';

// Set Vanilla to appear first in the list.
$Supported = array(
   'vanilla1' => array('name'=> 'Vanilla 1.*', 'prefix'=>'LUM_'),
   'vanilla2' => array('name'=> 'Vanilla 2.*', 'prefix'=>'GDN_')
);

// Include individual software porters.
// MAKESKIPSTART
$Paths = glob(dirname(__FILE__).'/class.*.php');
foreach ($Paths as $Path) {
   include_once $Path;
}
// MAKESKIPEND

include_once 'functions.commandline.php';

// Make sure a default time zone is set
if (ini_get('date.timezone') == '')
   date_default_timezone_set('America/Montreal');

if (PHP_SAPI == 'cli')
   define('CONSOLE', TRUE);

if (defined('CONSOLE')) {
   ParseCommandLine();
}

if (isset($_GET['type'])) {
   $CustomType = $_GET['type'];
   if (!isset($Supported[$CustomType])) {
      $Path = 'class.'.strtolower($CustomType).'.php';
      if (file_exists($Path)) {
         $Supported[$CustomType] = array('name' => $CustomType.' (custom)', 'prefix' => '');
         include_once($Path);
      }
   }
}

$Method = 'DoExport';
if (isset($_POST['doavatars']) && $_POST['doavatars'])
   $Method = 'DoAvatars';

// Instantiate the appropriate controller or display the input page.
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

if (defined('CONSOLE'))
   echo "\n";

function ErrorHandler($errno, $errstr) {
   echo "Error: ({$errno}) {$errstr}\n";
   die();
}

set_error_handler("ErrorHandler");

/**
 * Write out a value passed as bytes to its most readable format.
 */
function FormatMemorySize($Bytes, $Precision = 1) {
   $Units = array('B', 'K', 'M', 'G', 'T');

   $Bytes = max((int)$Bytes, 0);
   $Pow = floor(($Bytes ? log($Bytes) : 0) / log(1024));
   $Pow = min($Pow, count($Units) - 1);

   $Bytes /= pow(1024, $Pow);

   $Result = round($Bytes, $Precision).$Units[$Pow];
   return $Result;
}

/** 
 * Test filesystem permissions 
 */  
function TestWrite() {
   // Create file
   $file = 'vanilla2test.txt';
   @touch($file);
   if(is_writable($file)) {
      @unlink($file);
      return true;
   }
   else return false;
}
