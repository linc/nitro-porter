<?php
/**
 * All-purpose logic
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

// Make sure a default time zone is set
if (ini_get('date.timezone') == '')
   date_default_timezone_set('America/Montreal');

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

// Files
include('class.exportmodel.php');
include('views.php');
include('class.exportcontroller.php');

include('class.vanilla.php');
include('class.vbulletin.php');

// Logic
if(isset($_POST['type']) && array_key_exists($_POST['type'], $Supported)) {
   // Mini-Factory
   $class = ucwords($_POST['type']);
   $Controller = new $class();
   $Controller->DoExport();
}
else {
   // View form or error
   if(TestWrite())
      ViewForm($Supported);
   else
      ViewNoPermission("This script has detected that it does not have permission to create files in the current directory. Please rectify this and retry.");
}
?>