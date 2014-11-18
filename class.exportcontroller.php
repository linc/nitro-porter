<?php
/**
 * @copyright Vanilla Forums Inc. 2010-2015
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Generic controller implemented by forum-specific ones.
 */
abstract class ExportController {

   /** @var array Database connection info */
   protected $DbInfo = array();

   /** @var array Required tables, columns set per exporter */
   protected $SourceTables = array();

   /** @var bool Whether to stream result; deprecated. */
   protected $UseStreaming = FALSE;

   /** @var ExportModel */
   protected $Ex = NULL;

   /** Forum-specific export routine */
   abstract protected function ForumExport($Ex);

   /**
    * Construct and set the controller's properties from the posted form.
    */
   public function __construct() {
      $this->HandleInfoForm();
      
      $this->Ex = new ExportModel;
      $this->Ex->Controller = $this;
      $this->Ex->SetConnection($this->DbInfo['dbhost'], $this->DbInfo['dbuser'], $this->DbInfo['dbpass'], $this->DbInfo['dbname']);
      $this->Ex->Prefix = $this->DbInfo['prefix'];
      $this->Ex->Destination = $this->Param('dest', 'file');
      $this->Ex->DestDb = $this->Param('destdb', NULL);
      $this->Ex->TestMode = $this->Param('test', FALSE);
      $this->Ex->UseStreaming = FALSE; //$this->UseStreaming;
   }

   /**
    * Set CDN file prefix if one is given.
    *
    * @return string
    */
   public function CdnPrefix() {
      $Cdn = rtrim($this->Param('cdn', ''), '/');
      if ($Cdn)
         $Cdn .= '/';
      
      return $Cdn;
   }

   /**
    * Logic for export process.
    */
   public function DoExport() {
      global $Supported;

      // Test connection
      $Msg = $this->TestDatabase();
      if($Msg === true) {

         // Test src tables' existence structure
         $Msg = $this->Ex->VerifySource($this->SourceTables);
         if($Msg === true) {
            // Good src tables - Start dump
            $this->Ex->UseCompression(TRUE);
            $this->Ex->FilenamePrefix = $this->DbInfo['dbname'];
            set_time_limit(60*60);
            
//            ob_start();
            $this->ForumExport($this->Ex);
//            $Errors = ob_get_clean();
            
            $Msg = $this->Ex->Comments;

            // Write the results.
            if($this->Ex->UseStreaming)
               exit;
            else
               ViewExportResult($Msg, 'Info', $this->Ex->Path);
         }
         else
            ViewForm(array('Supported' => $Supported, 'Msg' => $Msg, 'Info' => $this->DbInfo)); // Back to form with error
      }
      else
         ViewForm(array('Supported' => $Supported, 'Msg' => $Msg, 'Info' => $this->DbInfo)); // Back to form with error
   }

   /**
    * User submitted db connection info.
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
    * Retrieve a parameter passed to the export process.
    *
    * @param string $Name
    * @param mixed $Default Fallback value.
    * @return mixed Value of the parameter.
    */
   public function Param($Name, $Default = FALSE) {
      if (isset($_POST[$Name]))
         return $_POST[$Name];
      elseif (isset($_GET[$Name]))
         return $_GET[$Name];
      else
         return $Default;
   }

   /**
    * Test database connection info.
    *
    * @return string|bool True on success, message on failure.
    */
   public function TestDatabase() {
      // Connection
      if($C = @mysql_connect($this->DbInfo['dbhost'], $this->DbInfo['dbuser'], $this->DbInfo['dbpass'])) {
         // Database
         if(mysql_select_db($this->DbInfo['dbname'], $C)) {
            mysql_close($C);
            $Result = true;
         }
         else {
            mysql_close($C);
            $Result = "Could not find database '{$this->DbInfo['dbname']}'.";
         }
      }
      else
         $Result = 'Could not connect to '.$this->DbInfo['dbhost'].' as '.$this->DbInfo['dbuser'].' with given password.';

      return $Result;
   }
}
?>