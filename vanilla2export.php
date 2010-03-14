<?php
/**
 * All-purpose logic
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/** @var array Supported forum packages: classname => DisplayName */
$supported = array(
   'vbulletin' => array('name'=>'vBulletin 3+', 'prefix'=>'vb_');

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
foreach($supported as $file => $info) {
   include('class.'.$file.'.php');
}

// Logic
if(isset($_POST['type']) && array_key_exists($_POST['type'], $supported)) {
   // Mini-Factory
   $class = ucwords($_POST['type']);
   new $class;
}
else {
   // View form or error
   if(TestWrite())
      ViewForm($supported);
   else
      ViewNoPermission();
}