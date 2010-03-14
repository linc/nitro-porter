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
   
   /** Forum-specific export routine */
   abstract protected function ForumExport();
   
   /** 
    * Instantation of descendant means form has been submitted
    * Setup model & views and go! 
    */
   public function __construct() {
      $this->DoExport();
   }
   
   /** 
    * Logic for export process 
    */
   public function DoExport() {
      $this->HandleInfoForm();
      $msg = $this->TestDatabase();
      if($msg===true) {
         //  Good connection info - Proceed
         $Ex = new ExportModel;
         $dsn = 'mysql:dbname='.$this->dbinfo['dbname'].';host='.$this->dbinfo['host'];
         $Ex->PDO($dsn, $this->dbinfo['dbuser'], $this->dbinfo['dbpass']);
         $Ex->Prefix = $this->dbinfo['prefix'];
         $Ex->UseCompression = TRUE;
         set_time_limit(60*2);
         $this->ForumExport();
      }
      else { // Back to form with error
         ViewForm($msg);
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
    * Test database connection info & integrity of forum data
    */
   public function TestDatabase() {
      // Connection
      if($c = mysql_connect($this->dbinfo['host'], $this->dbinfo['dbuser'], $this->dbinfo['dbpass'])) { 
         // Database
         if(mysql_select_db($this->dbinfo['dbname'], $c)) { // Tables exist
            mysql_close($c);
            return true;
         }
         else {
            mysql_close($c);
            return 'Could not find database &ldquo;'.$this->dbinfo['dbname'].'&rdquo;.';
         }
      }
      else 
         return 'Could not connect to '.$this->dbinfo['host'].' as '.$this->dbinfo['dbuser'].' with given password.';
   }
   
}