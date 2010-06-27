<?php
/**
 * Vanilla 2 exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
 
class Vanilla2 extends ExportController {

   /** @var array Required tables => columns */  
   protected $_SourceTables = array();
   
   /**
    * Forum-specific export format
    */
   protected function ForumExport($Ex) {
   
   }
   
}