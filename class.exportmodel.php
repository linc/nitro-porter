<?php
/**
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Object for exporting other database structures into a format that can be imported.
 */
class ExportModel {
   const COMMENT = '//';
   const DELIM = ',';
   const ESCAPE = '\\';
   const NEWLINE = "\n";
   const NULL = '\N';
   const QUOTE = '"';


   /** @var array Any comments that have been written during the export. */
   public $Comments = array();

   /** @var object File pointer */
   protected $_File = NULL;

   /** @var string A prefix to put into an automatically generated filename. */
   public $FilenamePrefix = '';

   protected $_Host;

   protected $_Limit = 20000;

   /** @var object PDO instance */
   protected $_PDO = NULL;

   protected $_Password;

   /** @var string The path to the export file. */
   public $Path = '';

   /**
    * @var string The database prefix. When you pass a sql string to ExportTable() it will replace occurances of :_ with this property.
    * @see ExportModel::ExportTable()
    */
   public $Prefix = '';

   /**
    * @var array Strucutes that define the format of the export tables.
    */
   protected $_Structures = array(
      'Activity' => array(
            'ActivityUserID' => 'int',
            'RegardingUserID' => 'int',
            'Story' => 'text',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime'),
      'Category' => array(
            'CategoryID' => 'int',
            'Name' => 'varchar(30)',
            'Description' => 'varchar(250)',
            'ParentCategoryID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'Sort' => 'int'),
      'Comment' => array(
            'CommentID' => 'int',
            'DiscussionID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'Format' => 'varchar(20)',
            'Body' => 'text',
            'Score' => 'float'),
      'Conversation' => array(
            'ConversationID' => 'int',
            'FirstMessageID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int'),
      'ConversationMessage' => array(
            'MessageID' => 'int',
            'ConversationID' => 'int',
            'Body' => 'text',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime'),
      'Discussion' => array(
            'DiscussionID' => 'int',
            'Name' => 'varchar(100)',
            'Body' => 'text',
            'Format' => 'varchar(20)',
            'CategoryID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'DateLastComment' => 'datetime',
            'CountComments' => 'int',
            'Score' => 'float',
            'Closed' => 'tinyint',
            'Announce' => 'tinyint',
            'Sink' => 'tinyint'),
      'Role' => array(
            'RoleID' => 'int',
            'Name' => 'varchar(100)',
            'Description' => 'varchar(200)',
            'CanSession' => 'tinyint'),
      'User' => array(
            'UserID' => 'int',
            'Name' => 'varchar(20)',
            'Email' => 'varchar(200)',
            'Password' => 'varbinary(34)',
            //'Gender' => array('m', 'f'),
            'Score' => 'float',
            'InviteUserID' => 'int',
            'HourOffset' => 'int',
            'CountDiscussions' => 'int',
            'CountComments' => 'int',
            'Photo' => 'varchar(255)',
            'DateOfBirth' => 'datetime',
            'DateFirstVisit' => 'datetime',
            'DateLastActive' => 'datetime',
            'DateInserted' => 'datetime',
            'DateUpdated' => 'datetime'),
      'UserConversation' => array(
            'UserID' => 'int',
            'ConversationID' => 'int',
            'LastMessageID' => 'int'),
      'UserDiscussion' => array(
            'UserID' => 'int',
            'DiscussionID' => 'int',
            'Bookmarked' => 'tinyint',
            'DateLastViewed' => 'datetime',
            'CountComments' => 'int'),
      'UserMeta' => array(
            'UserID' => 'int',
            'Name' => 'varchar(255)',
            'Value' => 'text'),
      'UserRole' => array(
            'UserID' => 'int',
            'RoleID' => 'int')
   );

   /**
    * @var bool Whether or not to use compression when creating the file.
    */
   protected $_UseCompression = TRUE;

   protected $_Username;

   /**
    *
    * @var bool Whether or not to stream the export the the output rather than save a file.
    */
   public $UseStreaming = FALSE;


   /**
    * Create the export file and begin the export.
    * @param string $Path The path to the export file.
    * @param string $Source The source program that created the export. This may be used by the import routine to do additional processing.
    */
   public function BeginExport($Path = '', $Source = '', $Header = array()) {
      $this->Comments = array();
      $this->BeginTime = microtime(TRUE);

      if($Path)
         $this->Path = $Path;
      if(!$this->Path)
         $this->Path = 'export '.($this->FilenamePrefix ? $this->FilenamePrefix.' ' : '').date('Y-m-d His').'.txt'.($this->UseCompression() ? '.gz' : '');

      $fp = $this->_OpenFile();

      fwrite($fp, 'Vanilla Export: '.$this->Version());
      if($Source)
         fwrite($fp, self::DELIM.' Source: '.$Source);
      foreach ($Header as $Key => $Value) {
         fwrite($fp, self::DELIM." $Key: $Value");
      }
      fwrite($fp, self::NEWLINE.self::NEWLINE);
      $this->Comment('Export Started: '.date('Y-m-d H:i:s'));
   }

   /**
    * Write a comment to the export file.
    * @param string $Message The message to write.
    * @param bool $Echo Whether or not to echo the message in addition to writing it to the file.
    */
   public function Comment($Message, $Echo = TRUE) {
      fwrite($this->_File, self::COMMENT.' '.str_replace(self::NEWLINE, self::NEWLINE.self::COMMENT.' ', $Message).self::NEWLINE);
      if($Echo)
         $this->Comments[] = $Message;
   }

   /**
    * End the export and close the export file. This method must be called if BeginExport() has been called or else the export file will not be closed.
    */
   public function EndExport() {
      $this->EndTime = microtime(TRUE);
      $this->TotalTime = $this->EndTime - $this->BeginTime;

      $this->Comment('Export Completed: '.date('Y-m-d H:i:s'));
      $this->Comment(sprintf('Elapsed Time: %s', self::FormatElapsed($this->TotalTime)));

      if($this->UseStreaming) {
         //ob_flush();
      } else {
         if($this->UseCompression() && function_exists('gzopen'))
            gzclose($this->_File);
         else
            fclose($this->_File);
      }
   }

   /**
    * Export a table to the export file.
    * @param string $TableName the name of the table to export. This must correspond to one of the accepted Vanilla tables.
    * @param mixed $Query The query that will fetch the data for the export this can be one of the following:
    *  - <b>String</b>: Represents a string of SQL to execute.
    *  - <b>PDOStatement</b>: Represents an already executed query result set.
    *  - <b>Array</b>: Represents an array of associative arrays or objects containing the data in the export.
    *  @param array $Mappings Specifies mappings, if any, between the source and the export where the keys represent the source columns and the values represent Vanilla columns.
    *	  - If you specify a Vanilla column then it must be in the export structure contained in this class.
    *   - If you specify a MySQL type then the column will be added.
    *   - If you specify an array you can have the following keys: Column, and Type where Column represents the new column name and Type represents the MySQL type.
    *  For a list of the export tables and columns see $this->Structure().
    */
   public function ExportTable($TableName, $Query, $Mappings = array()) {
      $BeginTime = microtime(TRUE);

      $RowCount = $this->_ExportTable($TableName, $Query, $Mappings);

      $EndTime = microtime(TRUE);
      $Elapsed = self::FormatElapsed($BeginTime, $EndTime);
      $this->Comment("Exported Table: $TableName ($RowCount rows, $Elapsed)");
      fwrite($this->_File, self::NEWLINE);
   }

   protected function _ExportTable($TableName, $Query, $Mappings = array()) {
      $fp = $this->_File;

      // Make sure the table is valid for export.
      if (!array_key_exists($TableName, $this->_Structures)) {
         $this->Comment("Error: $TableName is not a valid export."
            ." The valid tables for export are ". implode(", ", array_keys($this->_Structures)));
         fwrite($fp, self::NEWLINE);
         return;
      }
      $Structure = $this->_Structures[$TableName];

      // Set the search and replace to escape strings.
      $EscapeSearch = array(self::ESCAPE, self::DELIM, self::NEWLINE, self::QUOTE); // escape must go first
      $EscapeReplace = array(self::ESCAPE.self::ESCAPE, self::ESCAPE.self::DELIM, self::ESCAPE.self::NEWLINE, self::ESCAPE.self::QUOTE);

      $LastID = 0;
      $IDName = 'NOTSET';
      $FirstQuery = TRUE;

      // Get the filters from the mappings.
      $Filters = array();
      foreach ($Mappings as $Column => $Mapping) {
         if (is_array($Mapping) &&isset($Mapping['Column']) && isset($Mapping['Filter'])) {
            $Filters[$Mapping['Column']] = $Mapping['Filter'];
         }
      }

      $Data = $this->Query($Query, $IDName, $LastID, $this->_Limit);

      // Loop through the data and write it to the file.
      $RowCount = 0;
      if ($Data !== FALSE) {
         while (($Row = mysql_fetch_assoc($Data)) !== FALSE) {
            $Row = (array)$Row; // export%202010-05-06%20210937.txt
            $RowCount++;
            if($FirstQuery) {
               // Start with the table name.
               fwrite($fp, 'Table: '.$TableName.self::NEWLINE);

               // Get the export structure.
               $ExportStructure = $this->GetExportStructure($Row, $Structure, $Mappings);

               // Build and write the table header.
               $TableHeader = $this->_GetTableHeader($ExportStructure, $Structure);

               fwrite($fp, $TableHeader.self::NEWLINE);

               $Mappings = array_flip($Mappings);

               $FirstQuery = FALSE;
            }

            $First = TRUE;

            // Loop through the columns in the export structure and grab their values from the row.
            $ExRow = array();
            foreach ($ExportStructure as $Field => $Type) {
               // Get the value of the export.
               if (array_key_exists($Field, $Row)) {
                  // The column has an exact match in the export.
                  $Value = $Row[$Field];
               } elseif (array_key_exists($Field, $Mappings)) {
                  // The column is mapped.
                  $Value = $Row[$Mappings[$Field]];
               } else {
                  $Value = NULL;
               }
               // Format the value for writing.
               if (is_null($Value)) {
                  $Value = self::NULL;
               } elseif (is_numeric($Value)) {
                  // Do nothing, formats as is.
               } elseif (is_string($Value)) {

                  // Check to see if there is a callback filter.
                  if (isset($Filters[$Field])) {
                     $Value = call_user_func($Filters[$Field], $Value, $Field, $Row);
                  } else {
                     if(mb_detect_encoding($Value) != 'UTF-8')
                        $Value = utf8_encode($Value);
                  }

                     $Value = self::QUOTE
                     .str_replace($EscapeSearch, $EscapeReplace, $Value)
                     .self::QUOTE;
               } elseif (is_bool($Value)) {
                  $Value = $Value ? 1 : 0;
               } else {
                  // Unknown format.
                  $Value = self::NULL;
               }

               $ExRow[] = $Value;
            }
            // Write the data.
            fwrite($fp, implode(self::DELIM, $ExRow));
            // End the record.
            fwrite($fp, self::NEWLINE);
         }
      }
      if($Data !== FALSE)
         mysql_free_result($Data);
      unset($Data);

      // Write an empty line to signify the end of the table.
      fwrite($fp, self::NEWLINE);
      mysql_close();

      return $RowCount;
   }

   static function FormatElapsed($Start, $End = NULL) {
      if($End === NULL)
         $Elapsed = $Start;
      else
         $Elapsed = $End - $Start;

      $m = floor($Elapsed / 60);
      $s = $Elapsed - $m * 60;
      $Result = sprintf('%02d:%05.2f', $m, $s);

      return $Result;
   }

   public function GetExportStructure($Row, $Structure, &$Mappings) {
      $ExportStructure = array();
      // See what columns from the structure are in

      // See what columns to add to the end of the structure.
      foreach($Row as $Column => $X) {
         if(array_key_exists($Column, $Mappings)) {
            $Mapping = $Mappings[$Column];
            if(is_string($Mapping)) {
               if(array_key_exists($Mapping, $Structure)) {
                  // This an existing column.
                  $DestColumn = $Mapping;
                  $DestType = $Structure[$DestColumn];
               } else {
                  // This is a created column.
                  $DestColumn = $Column;
                  $DestType = $Mapping;
               }
            } elseif(is_array($Mapping)) {
               $DestColumn = $Mapping['Column'];
               if (isset($Mapping['Type']))
                  $DestType = $Mapping['Type'];
               elseif(isset($Structure[$DestColumn]))
                  $DestType = $Structure[$DestColumn];
               else
                  $DestType = 'varchar(255)';
               $Mappings[$Column] = $DestColumn;
            }
         } elseif(array_key_exists($Column, $Structure)) {
            $DestColumn = $Column;
            $DestType = $Structure[$Column];
         } else {
            $DestColumn = '';
            $DestType = '';
         }

         // Check to see if we have to add the column to the export structure.
         if($DestColumn && !array_key_exists($DestColumn, $ExportStructure)) {
            // TODO: Make sure $DestType is a valid MySQL type.
            $ExportStructure[$DestColumn] = $DestType;
         }
      }
      return $ExportStructure;
   }

   protected function _GetTableHeader($Structure, $GlobalStructure) {
      $TableHeader = '';

      foreach($Structure as $Column => $Type) {
         if(strlen($TableHeader) > 0)
            $TableHeader .= self::DELIM;
         if(array_key_exists($Column, $GlobalStructure)) {
            $TableHeader .= $Column;
         } else {
            $TableHeader .= $Column.':'.$Type;
         }
      }
      return $TableHeader;
   }
   
   /**
    * Decode the HTML out of a value.
    */
   public function HTMLDecoder($Value) {
      return html_entity_decode($Value, ENT_COMPAT, 'UTF-8');
   }

    /**
    * vBulletin needs some fields decoded and it won't hurt the others.
    */
//   public function HTMLDecoder($Table, $Field, $Value) {
//      if(($Table == 'Category' || $Table == 'Discussion') && $Field == 'Name')
//         return html_entity_decode($Value);
//      else
//         return $Value;
//   }


   protected function _OpenFile() {
      if($this->UseStreaming) {
         /** Setup the output to stream the file. */

         // required for IE, otherwise Content-Disposition may be ignored
         if(ini_get('zlib.output_compression'))
            ini_set('zlib.output_compression', 'Off');

         @ob_end_clean();

         
         $fp = fopen('php://output', 'a');

         header('Content-Type: text/plain');
         header("Content-Disposition: attachment; filename=\"{$this->Path}\"");
         header("Content-Transfer-Encoding: binary");
         header('Accept-Ranges: bytes');
         header("Cache-control: private");
         header('Pragma: private');
         header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
      } else {
         $this->Path = str_replace(' ', '_', $this->Path);
         if($this->UseCompression())
            $fp = gzopen($this->Path, 'wb');
         else
            $fp = fopen($this->Path, 'wb');
      }
      $this->_File = $fp;
      return $fp;
   }

   /** Execute a SQL query on the current connection.
    *
    * @param string $Query The sql to execute.
    * @return resource The query cursor.
    */
   public function Query($Query) {
      if (isset($this->_LastResult) && is_resource($this->_LastResult))
         mysql_free_result($this->_LastResult);
      $Query = str_replace(':_', $this->Prefix, $Query); // replace prefix.

      $Connection = mysql_connect($this->_Host, $this->_Username, $this->_Password);
      mysql_select_db($this->_DbName);
      mysql_query('set names utf8');
      $Result = mysql_unbuffered_query($Query, $Connection);

      if ($Result === FALSE) {
         trigger_error(mysql_error($Connection));
      }

      $this->_LastResult = $Result;
      
      return $Result;
   }
   
   public function SetConnection($Host = NULL, $Username = NULL, $Password = NULL, $DbName = NULL) {
      $this->_Host = $Host;
      $this->_Username = $Username;
      $this->_Password = $Password;
      $this->_DbName = $DbName;
   }

   /**
    * Returns an array of all the expected export tables and expected columns in the exports.
    * When exporting tables using ExportTable() all of the columns in this structure will always be exported in the order here, regardless of how their order in the query.
    * @return array
    * @see vnExport::ExportTable()
    */
   public function Structures() {
      return $this->_Structures;
   }

   /**
    * Whether or not to use compression on the output file.
    * @param bool $Value The value to set or NULL to just return the value.
    * @return bool
    */
   public function UseCompression($Value = NULL) {
      if($Value !== NULL)
         $this->_UseCompression = $Value;

      return $this->_UseCompression && !$this->UseStreaming && function_exists('gzopen');
   }

   /**
    * Returns the version of export file that will be created with this export.
    * The version is used when importing to determine the format of this file.
    * @return string
    */
   public function Version() {
      return '1.0';
   }

   /**
    * Checks all required source tables are present
    */
   public function VerifySource($RequiredTables) {
      $MissingTables = false;
      $CountMissingTables = 0;
      $MissingColumns = array();

      foreach($RequiredTables as $ReqTable => $ReqColumns) {
         $TableDescriptions = $this->Query('describe :_'.$ReqTable);
         //echo 'describe '.$Prefix.$ReqTable;
         if($TableDescriptions === false) { // Table doesn't exist
            $CountMissingTables++;
            if($MissingTables !== false)
               $MissingTables .= ', '.$ReqTable;
            else
               $MissingTables = $ReqTable;
         }
         else {
            // Build array of columns in this table
            $PresentColumns = array();
            while (($TD = mysql_fetch_assoc($TableDescriptions)) !== false) {
               $PresentColumns[] = $TD['Field'];
            }
            // Compare with required columns
            foreach($ReqColumns as $ReqCol) {
               if(!in_array($ReqCol, $PresentColumns))
                  $MissingColumns[$ReqTable][] = $ReqCol;
            }

            mysql_free_result($TableDescriptions);
         }
      }

      // Return results
      if($MissingTables===false) {
         if(count($MissingColumns) > 0) {
         }
         else return true; // Nothing missing!
      }
      elseif($CountMissingTables == count($RequiredTables)) {
         return 'The required tables are not present in the database. Make sure you entered the correct database name and prefix and try again.';
      }
      else {
         return 'Missing required database tables: '.$MissingTables;
      }
   }
}
?>