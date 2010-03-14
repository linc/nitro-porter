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
   
   /** @var object PDO database connection instance */
   public $db;
   
   /** 
    * Instantation of descendant means form has been submitted
    * Setup model & views and go! 
    */
   public function __construct() {
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
      $db = $this->TestConnection();
      if($db===true) {
         //  Good connection info - Proceed
         $Ex = new ExportModel;
         $dsn = 'mysql:dbname='.$this->dbinfo['dbname'].';host='.$this->dbinfo['host'];
         $Ex->PDO($dsn, $this->dbinfo['dbuser'], $this->dbinfo['dbpass']);
         $Ex->Prefix = $this->dbinfo['prefix'];
         $Ex->UseCompression = TRUE;
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
         'dbhost'=>$_POST['dbhost'],
         'dbuser'=>$_POST['dbuser'], 
         'dbpass'=>$_POST['dbpass'], 
         'dbname'=>$_POST['dbname'],
         'prefix'=>preg_replace('/[^A-Za-z0-9_-]/','',$_POST['prefix']));
   }
   
   /** 
    * Test database connection info 
    */
   public function TestConnection() {
      if($c = mysql_connect($this->dbinfo['host'], $this->dbinfo['dbuser'], $this->dbinfo['dbpass'])) {
         mysql_close($c);
         return true;
      }
      else return 'Could not connect';
   }
 
   
}