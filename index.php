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
define('APPLICATION_VERSION', '1.7.1');

if(TRUE || defined('DEBUG'))
   error_reporting(E_ALL);
else
   error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);
 
global $Supported;

/** @var array Supported forum packages: classname => array(name, prefix) */
$Supported = array(
   'vanilla1' => array('name'=> 'Vanilla 1.*', 'prefix'=>'LUM_'),
   'vanilla2' => array('name'=> 'Vanilla 2.*', 'prefix'=>'GDN_'),
   'vbulletin' => array('name'=>'vBulletin 3.* and 4.*', 'prefix'=>'vb_'),
   'phpbb2' => array('name'=>'phpBB 2.*', 'prefix' => 'phpbb_'),
   'phpbb3' => array('name'=>'phpBB 3.*', 'prefix' => 'phpbb_'),
   'bbPress' => array('name'=>'bbPress 1.*', 'prefix' => 'bb_'),
   'SimplePress' => array('name'=>'SimplePress 1.*', 'prefix' => 'wp_'),
   'SMF' => array('name'=>'SMF (Simple Machines) 1.*', 'prefix' => 'smf_'),
   'punbb' => array('name'=>'PunBB 1.*', 'prefix' => 'punbb_')
);

// Support Files
include_once 'class.exportmodel.php';
include_once 'views.php';
include_once 'class.exportcontroller.php';
include_once 'functions.php';

include_once 'class.vanilla1.php';
include_once 'class.vanilla2.php';
include_once 'class.vbulletin.php';
include_once 'class.phpbb2.php';
include_once 'class.phpbb3.php';
include_once 'class.bbpress.php';
include_once 'class.simplepress.php';
include_once 'class.smf.php';
include_once 'class.punbb.php';
include_once 'class.kunena.php';

// Include any misc porters that haven't been included yet.
$Paths = glob(dirname(__FILE__).'/class.*.php');
foreach ($Paths as $Path) {
   include_once $Path;
}

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
if (isset($_POST['avatars']) && $_POST['avatars'])
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
