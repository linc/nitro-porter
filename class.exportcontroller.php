<?php
/**
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/** Generic controller implemented by forum-specific ones */
abstract class ExportController {  
   
   /** @var array Database connection info */
   protected $dbinfo = array();
   
   /** 
    * Instantation of descendant means form has been submitted
    * Setup model & views and go! 
    */
   public function __construct() {
      $this->ExportModel = new ExportModel;
      $this->View = new ExportViews;
      $this->DoExport();
   }

   /** Forum-specific data integrity check */
   abstract protected function VerifyStructure();
   
   /** Forum-specific export routine */
   abstract protected function ForumExport();
   
   /** 
    * Logic for export process 
    */
   public function DoExport() {
      $this->HandleInfoForm();
      
      if($this->TestConnection()) {
         //  Good connection - Proceed
         
      }
      else {
         // Back to form with error
         
      }
   }
   
   /** 
    * User submitted db connection info 
    */
   public function HandleInfoForm() {
      $this->dbinfo = array(
         'dbserv'=>$_POST['dbserv'],
         'dbuser'=>$_POST['dbuser'], 
         'dbpass'=>$_POST['dbpass'], 
         'dbname'=>$_POST['dbname'],
         'prefix'=>preg_replace('/[^A-Za-z0-9_-]/','',$_POST['prefix']));
   }
   
   /** 
    * Test database connection info 
    */
   public function TestConnection() {
      
   }      
   
}