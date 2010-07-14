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

   /** @var array Required tables, columns set per exporter */
   protected $SourceTables = array();

   protected $UseStreaming = FALSE;

   /** Forum-specific export routine */
   abstract protected function ForumExport($Ex);

   /**
    * Construct and set the controller's properties from the posted form.
    */
   public function __construct() {
      $this->HandleInfoForm();
   }

   /**
    * Logic for export process
    */
   public function DoExport() {
      global $Supported;

      // Test connection
      $Msg = $this->TestDatabase();
      if($Msg === true) {
         // Create db object
         $Ex = new ExportModel;
         $Ex->SetConnection($this->DbInfo['dbhost'], $this->DbInfo['dbuser'], $this->DbInfo['dbpass'], $this->DbInfo['dbname']);
         $Ex->Prefix = $this->DbInfo['prefix'];
         $Ex->UseStreaming = $this->UseStreaming;
         // Test src tables' existence structure
         $Msg = $Ex->VerifySource($this->SourceTables);
         if($Msg === true) {
            // Good src tables - Start dump
            $Ex->UseCompression(TRUE);
            $Ex->FilenamePrefix = $this->DbInfo['dbname'];
            set_time_limit(60*60);
            $this->ForumExport($Ex);

            // Write the results.
            if($Ex->UseStreaming)
               exit;
            else
               ViewExportResult($Ex->Comments, 'Info', $Ex->Path);
         }
         else
            ViewForm(array('Supported' => $Supported, 'Msg' => $Msg, 'Info' => $this->DbInfo)); // Back to form with error
      }
      else
         ViewForm(array('Supported' => $Supported, 'Msg' => $Msg, 'Info' => $this->DbInfo)); // Back to form with error
   }

   /**
    * User submitted db connection info
    */
   public function HandleInfoForm() {
      $this->DbInfo = array(
         'dbhost' => $_POST['dbhost'],
         'dbuser' => $_POST['dbuser'],
         'dbpass' => $_POST['dbpass'],
         'dbname' => $_POST['dbname'],
         'type'   => $_POST['type'],
         'prefix' => preg_replace('/[^A-Za-z0-9_-]/','',$_POST['prefix']));
      $this->UseStreaming = array_key_exists('savefile', $_POST) ? FALSE : TRUE;
   }

   /**
    * Test database connection info
    */
   public function TestDatabase() {
      // Connection
      if($C = @mysql_connect($this->DbInfo['dbhost'], $this->DbInfo['dbuser'], $this->DbInfo['dbpass'])) {
         // Database
         if(mysql_select_db($this->DbInfo['dbname'], $C)) {
            mysql_close($C);
            return true;
         }
         else {
            mysql_close($C);
            return 'Could not find database &ldquo;'.$this->DbInfo['dbname'].'&rdquo;.';
         }
      }
      else
         return 'Could not connect to '.$this->DbInfo['dbhost'].' as '.$this->DbInfo['dbuser'].' with given password.';
   }
}
?>