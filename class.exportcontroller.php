<?php
/**
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/** Generic controller implemented by forum-specific ones */
abstract class ExportController {  
   
   /** @var array Database connection info */
   protected $DbInfo = array();

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
      // Test connection
      $msg = $this->TestDatabase();
      if($msg===true) {
         // Good connection
         $Ex = new ExportModel;
         $dsn = 'mysql:dbname='.$this->DbInfo['dbname'].';host='.$this->DbInfo['host'];
         $Ex->PDO($dsn, $this->DbInfo['dbuser'], $this->DbInfo['dbpass']);
         $Ex->Prefix = $this->DbInfo['prefix'];
         // Test src tables' existence structure
         $msg = $Ex->VerifyStructure($this->SourceTables);
         if($msg===true) {
            // Good src tables
            $Ex->UseCompression = TRUE;
            set_time_limit(60*2);
            $Ex->ForumExport();
         }
      }
      else { // Back to form with error
         ViewForm($msg);
      }
   }
   
   /** 
    * User submitted db connection info 
    */
   public function HandleInfoForm() {
      $this->DbInfo = array(
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
      if($c = mysql_connect($this->DbInfo['host'], $this->DbInfo['dbuser'], $this->DbInfo['dbpass'])) { 
         // Database
         if(mysql_select_db($this->DbInfo['dbname'], $c)) { 
            mysql_close($c);
            return true;
         }
         else {
            mysql_close($c);
            return 'Could not find database &ldquo;'.$this->DbInfo['dbname'].'&rdquo;.';
         }
      }
      else 
         return 'Could not connect to '.$this->DbInfo['host'].' as '.$this->DbInfo['dbuser'].' with given password.';
   }
   
}