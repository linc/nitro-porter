<?php
/**
 * Vanilla 2 Exporter
 * This script exports other forum databases to the Vanilla 2 import format.
 * To use this script copy it to your web server and open it in your browser.
 * If you have a larger database the directory should be writable so that the export file can be saved locally and zipped.
 *
 * Copyright 2010 Vanilla Forums Inc.
 * This file is part of Garden.
 * Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
 * Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
 *
 * @package VanillaPorter
 */

error_reporting(E_ALL);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);
 
global $Supported;

/** @var array Supported forum packages: classname => array(name, prefix) */
$Supported = array(
   'vanilla1' => array('name'=> 'Vanilla 1.x', 'prefix'=>'LUM_'),
   'vbulletin' => array('name'=>'vBulletin 3+', 'prefix'=>'vb_')
);

// Support Files
include('class.exportmodel.php');
include('views.php');
include('class.exportcontroller.php');

include('class.vanilla1.php');
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
?>