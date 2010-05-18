<?php
/**
 * Vanilla 2 Exporter
 * This script exports other forum databases to the Vanilla 2 import format.
 * To use this script copy it to your web server and open it in your browser.
 * If you have a larger database the directory should be writable so that the export file can be saved locally and zipped.
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
 
global $Supported;

/** @var array Supported forum packages: classname => array(name, prefix) */
$Supported = array(
   'vbulletin' => array('name'=>'vBulletin 3+', 'prefix'=>'vb_'),
   'vanilla' => array('name'=> 'Vanilla 1.x', 'prefix'=>'LUM_')
);

// Support Files
include('class.exportmodel.php');
include('views.php');
include('class.exportcontroller.php');

include('class.vanilla.php');
include('class.vbulletin.php');

// Make sure a default time zone is set
if (ini_get('date.timezone') == '')
   date_default_timezone_set('America/Montreal');

// Instantiate the appropriate controller or display the input page.
if(isset($_POST['type']) && array_key_exists($_POST['type'], $Supported)) {
   // Mini-Factory
   $class = ucwords($_POST['type']);
   $Controller = new $class();
   $Controller->DoExport();
}
else {
   $CanWrite = TestWrite();
   ViewForm(array('Supported' => $Supported, 'CanWrite' => $CanWrite));
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
?>