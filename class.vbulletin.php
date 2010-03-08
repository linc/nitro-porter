<?php
/**
 * vBulletin-specific exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
 
class Vbulletin extends ExportController {

   public $default_prefix = 'vb_';
      
   /** Make sure all the required vBulletin tables are present */
   public function VerifyStructure() {
      
   }
   
   
   public function ForumExport() {
      $Ex = $this->export;
      $Ex->PDO(Gdn::Database()->Connection());
      $Ex->Prefix = Gdn::Database()->DatabasePrefix;
      $Ex->UseCompression = TRUE;
      $Ex->BeginExport(PATH_ROOT.DS.'uploads'.DS.'export '.date('Y-m-d His').'.txt.gz', 'Vanilla 2.0');
      $Ex->ExportTable('User', 'select * from :_User'); // ":_" will be replace by database prefix
      $Ex->ExportTable('Role', 'select * from :_Role');
      $Ex->ExportTable('UserRole', 'select * from :_UserRole');
      $Ex->ExportTable('Category', 'select * from :_Category');
      $Ex->ExportTable('Discussion', 'select * from :_Discussion');
      $Ex->ExportTable('Comment', 'select * from :_Comment');
      $Ex->EndExport();
   }
   
}