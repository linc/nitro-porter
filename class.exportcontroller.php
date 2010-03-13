<?php
/**
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/** Generic controller */
abstract class ExportController {

   /** @var array Supported forum packages */
   public $supported = array('vBulletin');

   /** Forum-specific data integrity check */
   abstract protected function VerifyStructure();
   
   /** Forum-specific export routine */
   abstract protected function ForumExport();
   
   /** Setup model & views */
   public __construct() {
      $this->ExportModel = new ExportModel;
      $this->View = new ExportViews;
      $this->GetStep();
   }
   
   /** Kludgy logic method */
   public GetStep() {
      if(isset($_POST['step'])) {
         switch($_POST['step']) {
            case 'info': 
               $this->HandleInfoForm(); 
               break;
         }
      }
      else 
         $this->View->InfoForm();
   }
   
   /** User submitted db connection info */
   public HandleInfoForm() {
      $_SESSION['exportforum'] = array(
         'dbserv'=>$_POST['dbserv'],
         'dbuser'=>$_POST['dbuser'], 
         'dbpass'=>$_POST['dbpass'], 
         'dbname'=>$_POST['dbname'],
         'prefix'=>preg_replace('/[^A-Za-z0-9_-]/','',$_POST['prefix']));
   }
   
   public TestConnection() {
      
   }
   
   public TestWrite() {
      
   }
      
   
}