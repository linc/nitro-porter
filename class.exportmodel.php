<?php
/**
 * @copyright Vanilla Forums Inc. 2010-2015
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Object for exporting other database structures into a format that can be imported.
 */
class ExportModel
{

    /** Character constants. */
    const COMMENT = '//';
    const DELIM = ',';
    const ESCAPE = '\\';
    const NEWLINE = "\n";
    const NULL = '\N';
    const QUOTE = '"';

    /** @var bool */
    public $CaptureOnly = false;

    /** @var array Any comments that have been written during the export. */
    public $Comments = array();

    /** @var ExportController * */
    public $Controller = null;

    /** @var string The charcter set to set as the connection anytime the database connects. */
    public $CharacterSet = 'utf8';

    /** @var int The chunk size when exporting large tables. */
    public $ChunkSize = 100000;

    /** @var array * */
    public $CurrentRow = null;

    /** @var string Where we are sending this export: 'file' or 'database'. * */
    public $Destination = 'file';

    /** @var string * */
    public $DestPrefix = 'GDN_z';

    /** @var array * */
    public static $EscapeSearch = array();

    /** @var array * */
    public static $EscapeReplace = array();

    /** @var object File pointer */
    public $File = null;

    /** @var string A prefix to put into an automatically generated filename. */
    public $FilenamePrefix = '';

    /** @var string Database host. * */
    public $_Host = 'localhost';

    /** @var bool Whether mb_detect_encoding() is available. * */
    public static $Mb = false;

    /** @var object PDO instance */
    protected $_PDO = null;

    /** @var string Database password. * */
    protected $_Password;

    /** @var string The path to the export file. */
    public $Path = '';

    /**
     * @var string The database prefix. When you pass a sql string to ExportTable() it will replace occurances of :_ with this property.
     * @see ExportModel::ExportTable()
     */
    public $Prefix = '';

    /** @var array * */
    public $Queries = array();

    /** @var array * */
    protected $_QueryStructures = array();

    /** @var string The path to the source of the export in the case where a file is being converted. */
    public $SourcePath = '';

    /** @var string */
    public $SourcePrefix = '';

    /** @var bool * */
    public $ScriptCreateTable = true;

    /** @var array Structures that define the format of the export tables. */
    protected $_Structures = array();

    /** @var bool Whether to limit results to the $TestLimit. */
    public $TestMode = false;

    /** @var int How many records to limit when $TestMode is enabled. */
    public $TestLimit = 10;

    /** @var bool Whether or not to use compression when creating the file. */
    protected $_UseCompression = true;

    /** @var string Database username. */
    protected $_Username;

    /** @var bool Whether or not to stream the export the the output rather than save a file. */
    public $UseStreaming = false;

    /**
     * Setup.
     */
    public function __construct() {
        self::$Mb = function_exists('mb_detect_encoding');

        // Set the search and replace to escape strings.
        self::$EscapeSearch = array(self::ESCAPE, self::DELIM, self::NEWLINE, self::QUOTE); // escape must go first
        self::$EscapeReplace = array(
            self::ESCAPE . self::ESCAPE,
            self::ESCAPE . self::DELIM,
            self::ESCAPE . self::NEWLINE,
            self::ESCAPE . self::QUOTE
        );

        // Load structure.
        $this->_Structures = VanillaStructure();
    }

    /**
     * Create the export file and begin the export.
     *
     * @param string $Path The path to the export file.
     * @param string $Source The source program that created the export. This may be used by the import routine to do additional processing.
     * @param array $Header
     * @return resource Pointer to the file created.
     */
    public function BeginExport($Path = '', $Source = '', $Header = array()) {
        $this->Comments = array();
        $this->BeginTime = microtime(true);

        // Allow us to define where the output file goes.
        if ($Path) {
            $this->Path = $Path;
        } elseif ($this->Controller->Param('destpath')) {
            $this->Path = $this->Controller->Param('destpath');
            if (strstr($this->Path, '/') !== false && substr($this->Path, 1, -1) != '/') {
                // We're using slash paths but didn't include a final slash.
                $this->Path .= '/';
            }
        }

        // Allow the $Path parameter to override this default naming.
        if (!$Path) {
            $this->Path .= 'export_' . ($this->FilenamePrefix ? $this->FilenamePrefix . '_' : '') . date('Y-m-d_His') . '.txt' . ($this->UseCompression() ? '.gz' : '');
        }

        // Start the file pointer.
        $fp = $this->_OpenFile();

        // Build meta info about where this file came from.
        $Comment = 'Vanilla Export: ' . $this->Version();
        if ($Source) {
            $Comment .= self::DELIM . ' Source: ' . $Source;
        }
        foreach ($Header as $Key => $Value) {
            $Comment .= self::DELIM . " $Key: $Value";
        }

        // Add meta info to the output.
        if ($this->CaptureOnly) {
            $this->Comment($Comment);
        } else {
            fwrite($fp, $Comment . self::NEWLINE . self::NEWLINE);
        }

        $this->Comment('Export Started: ' . date('Y-m-d H:i:s'));

        return $fp;
    }

    /**
     * Write a comment to the export file.
     *
     * @param string $Message The message to write.
     * @param bool $Echo Whether or not to echo the message in addition to writing it to the file.
     */
    public function Comment($Message, $Echo = true) {
        if ($this->Destination == 'file') {
            $Char = self::COMMENT;
        } else {
            $Char = '--';
        }

        $Comment = $Char . ' ' . str_replace(self::NEWLINE, self::NEWLINE . self::COMMENT . ' ',
                $Message) . self::NEWLINE;

        fwrite($this->File, $Comment);
        if ($Echo) {
            if (defined('CONSOLE')) {
                echo $Comment;
            } else {
                $this->Comments[] = $Message;
            }
        }
    }

    /**
     * End the export and close the export file.
     *
     * This method must be called if BeginExport() has been called or else the export file will not be closed.
     */
    public function EndExport() {
        $this->EndTime = microtime(true);
        $this->TotalTime = $this->EndTime - $this->BeginTime;

        $this->Comment($this->Path);
        $this->Comment('Export Completed: ' . date('Y-m-d H:i:s'));
        $this->Comment(sprintf('Elapsed Time: %s', self::FormatElapsed($this->TotalTime)));

        if ($this->TestMode || $this->Controller->Param('dumpsql') || $this->CaptureOnly) {
            $Queries = implode("\n\n", $this->Queries);
            if ($this->Destination == 'database') {
                fwrite($this->File, $Queries);
            } else {
                $this->Comment($Queries, true);
            }
        }

        if ($this->UseStreaming) {
            //ob_flush();
        } else {
            if ($this->UseCompression() && function_exists('gzopen')) {
                gzclose($this->File);
            } else {
                fclose($this->File);
            }
        }
    }

    /**
     * Export a table to the export file.
     *
     * @param string $TableName the name of the table to export. This must correspond to one of the accepted Vanilla tables.
     * @param mixed $Query The query that will fetch the data for the export this can be one of the following:
     *  - <b>String</b>: Represents a string of SQL to execute.
     *  - <b>PDOStatement</b>: Represents an already executed query result set.
     *  - <b>Array</b>: Represents an array of associative arrays or objects containing the data in the export.
     * @param array $Mappings Specifies mappings, if any, between the source and the export where the keys represent the source columns and the values represent Vanilla columns.
     *      - If you specify a Vanilla column then it must be in the export structure contained in this class.
     *   - If you specify a MySQL type then the column will be added.
     *   - If you specify an array you can have the following keys: Column, and Type where Column represents the new column name and Type represents the MySQL type.
     *  For a list of the export tables and columns see $this->Structure().
     */
    public function ExportTable($TableName, $Query, $Mappings = array()) {
        $BeginTime = microtime(true);

        $RowCount = $this->_ExportTable($TableName, $Query, $Mappings);

        $EndTime = microtime(true);
        $Elapsed = self::FormatElapsed($BeginTime, $EndTime);
        $this->Comment("Exported Table: $TableName ($RowCount rows, $Elapsed)");
        fwrite($this->File, self::NEWLINE);
    }

    /**
     *
     *
     * @param $TableName
     * @param $Query
     * @param array $Mappings
     */
    protected function _ExportTableImport($TableName, $Query, $Mappings = array()) {
        // Backup the settings.
        $DestinationBak = $this->Destination;
        $this->Destination = 'file';

        $_FileBak = $this->File;
        $Path = dirname(__FILE__) . '/' . $TableName . '.csv';
        $this->Comment("Exporting To: $Path");
        $fp = fopen($Path, 'wb');
        $this->File = $fp;

        // First export the file to a file.
        $this->_ExportTable($TableName, $Query, $Mappings, array('NoEndline' => true));

        // Now define a table to import into.
        $this->_CreateExportTable($TableName, $Query, $Mappings);

        // Now load the data.
        $Sql = "load data local infile '$Path' into table {$this->DestDb}.{$this->DestPrefix}$TableName
         character set utf8
         columns terminated by ','
         optionally enclosed by '\"'
         escaped by '\\\\'
         lines terminated by '\\n'
         ignore 2 lines";
        $this->Query($Sql);

        // Restore the settings.
        $this->Destination = $DestinationBak;
        $this->File = $_FileBak;
    }

    /**
     * Convert database blobs into files.
     *
     * @param $Sql
     * @param $BlobColumn
     * @param $PathColumn
     * @param bool $Thumbnail
     */
    public function ExportBlobs($Sql, $BlobColumn, $PathColumn, $Thumbnail = false) {
        $this->Comment("Exporting blobs...");

        $Result = $this->Query($Sql);
        $Count = 0;
        while ($Row = mysql_fetch_assoc($Result)) {
            // vBulletin attachment hack (can't do this in MySQL)
            if (strpos($Row[$PathColumn], '.attach') && strpos($Row[$PathColumn], 'attachments/') !== false) {
                $PathParts = explode('/', $Row[$PathColumn]); // 3 parts

                // Split up the userid into a path, digit by digit
                $n = strlen($PathParts[1]);
                $DirParts = array();
                for ($i = 0; $i < $n; $i++) {
                    $DirParts[] = $PathParts[1]{$i};
                }
                $PathParts[1] = implode('/', $DirParts);

                // Rebuild full path
                $Row[$PathColumn] = implode('/', $PathParts);
            }

            $Path = $Row[$PathColumn];

            // Build path
            if (!file_exists(dirname($Path))) {
                $R = mkdir(dirname($Path), 0777, true);
                if (!$R) {
                    die("Could not create " . dirname($Path));
                }
            }

            if ($Thumbnail) {
                $PicPath = str_replace('/avat', '/pavat', $Path);
                $fp = fopen($PicPath, 'wb');
            } else {
                $fp = fopen($Path, 'wb');
            }
            if (!is_resource($fp)) {
                die("Could not open $Path.");
            }

            fwrite($fp, $Row[$BlobColumn]);
            fclose($fp);
            $this->Status('.');

            if ($Thumbnail) {
                if ($Thumbnail === true) {
                    $Thumbnail = 50;
                }

                $ThumbPath = str_replace('/avat', '/navat', $Path);
                GenerateThumbnail($PicPath, $ThumbPath, $Thumbnail, $Thumbnail);
            }
            $Count++;
        }
        $this->Status("$Count Blobs.\n");
        $this->Comment("$Count Blobs.", false);
    }

    /**
     * Process for writing an entire single table to file.
     *
     * @see ExportTable()
     * @param $TableName
     * @param $Query
     * @param array $Mappings
     * @param array $Options
     * @return int
     */
    protected function _ExportTable($TableName, $Query, $Mappings = array(), $Options = array()) {
        $fp = $this->File;

        // Make sure the table is valid for export.
        if (!array_key_exists($TableName, $this->_Structures)) {
            $this->Comment("Error: $TableName is not a valid export."
                . " The valid tables for export are " . implode(", ", array_keys($this->_Structures)));
            fwrite($fp, self::NEWLINE);

            return;
        }

        if ($this->Destination == 'database') {
            $this->_ExportTableDB($TableName, $Query, $Mappings);

            return;
        }

        // Check for a chunked query.
        $Query = str_replace('{from}', -2000000000, $Query);
        $Query = str_replace('{to}', 2000000000, $Query);

        if (strpos($Query, '{from}') !== false) {
            $this->_ExportTableDBChunked($TableName, $Query, $Mappings);

            return;
        }

        // If we are in test mode then limit the query.
        if ($this->TestMode && $this->TestLimit) {
            $Query = rtrim($Query, ';');
            if (stripos($Query, 'select') !== false && stripos($Query, 'limit') === false) {
                $Query .= " limit {$this->TestLimit}";
            }
        }

        $Structure = $this->_Structures[$TableName];

        $LastID = 0;
        $IDName = 'NOTSET';
        $FirstQuery = true;

        $Data = $this->Query($Query);

        // Loop through the data and write it to the file.
        $RowCount = 0;
        if ($Data !== false) {
            while (($Row = mysql_fetch_assoc($Data)) !== false) {
                $Row = (array)$Row; // export%202010-05-06%20210937.txt
                $this->CurrentRow =& $Row;
                $RowCount++;

                if ($FirstQuery) {
                    // Get the export structure.
                    $ExportStructure = $this->GetExportStructure($Row, $Structure, $Mappings, $TableName);
                    $RevMappings = $this->FlipMappings($Mappings);
                    $this->WriteBeginTable($fp, $TableName, $ExportStructure);

                    $FirstQuery = false;
                }
                $this->WriteRow($fp, $Row, $ExportStructure, $RevMappings);
            }
        }
        if ($Data !== false) {
            mysql_free_result($Data);
        }
        unset($Data);

        if (!isset($Options['NoEndline'])) {
            $this->WriteEndTable($fp);
        }

        mysql_close();

        return $RowCount;
    }

    /**
     *
     *
     * @param $TableName
     * @param $Query
     * @param array $Mappings
     */
    protected function _CreateExportTable($TableName, $Query, $Mappings = array()) {
        if (!$this->ScriptCreateTable) {
            return;
        }

        // Limit the query to grab any additional columns.
        $QueryStruct = rtrim($Query, ';') . ' limit 1';
        $Structure = $this->_Structures[$TableName];

        $Data = $this->Query($QueryStruct, true);
//      $Mb = function_exists('mb_detect_encoding');

        // Loop through the data and write it to the file.
        if ($Data === false) {
            return;
        }

        // Get the export structure.
        while (($Row = mysql_fetch_assoc($Data)) !== false) {
            $Row = (array)$Row;

            // Get the export structure.
            $ExportStructure = $this->GetExportStructure($Row, $Structure, $Mappings, $TableName);

            break;
        }
        mysql_close($Data);

        // Build the create table statement.
        $ColumnDefs = array();
        foreach ($ExportStructure as $ColumnName => $Type) {
            $ColumnDefs[] = "`$ColumnName` $Type";
        }
        $DestDb = '';
        if (isset($this->DestDb)) {
            $DestDb = $this->DestDb . '.';
        }

        $this->Query("drop table if exists {$DestDb}{$this->DestPrefix}$TableName");
        $CreateSql = "create table {$DestDb}{$this->DestPrefix}$TableName (\n  " . implode(",\n  ",
                $ColumnDefs) . "\n) engine=innodb";

        $this->Query($CreateSql);
    }

    /**
     *
     *
     * @see _ExportTable()
     * @param $TableName
     * @param $Query
     * @param array $Mappings
     */
    protected function _ExportTableDB($TableName, $Query, $Mappings = array()) {
        if ($this->HasFilter($Mappings) || strpos($Query, 'union all') !== false) {
            $this->_ExportTableImport($TableName, $Query, $Mappings);

            return;
        }

        // Check for a chunked query.
        if (strpos($Query, '{from}') !== false) {
            $this->_ExportTableDBChunked($TableName, $Query, $Mappings);

            return;
        }

        $DestDb = '';
        if (isset($this->DestDb)) {
            $DestDb = $this->DestDb . '.';
        }

        // Limit the query to grab any additional columns.
        $QueryStruct = $this->GetQueryStructure($Query, $TableName);
        $Structure = $this->_Structures[$TableName];

        $ExportStructure = $this->GetExportStructure($QueryStruct, $Structure, $Mappings, $TableName);

        $Mappings = $this->FlipMappings($Mappings);

        // Build the create table statement.
        $ColumnDefs = array();
        foreach ($ExportStructure as $ColumnName => $Type) {
            $ColumnDefs[] = "`$ColumnName` $Type";
        }
        if ($this->ScriptCreateTable) {
            $this->Query("drop table if exists {$DestDb}{$this->DestPrefix}$TableName");
            $CreateSql = "create table {$DestDb}{$this->DestPrefix}$TableName (\n  " . implode(",\n  ",
                    $ColumnDefs) . "\n) engine=innodb";
            $this->Query($CreateSql);
        }

        $Query = rtrim($Query, ';');
        // Build the insert statement.
        if ($this->TestMode && $this->TestLimit) {
            $Query .= " limit {$this->TestLimit}";
        }

        $InsertColumns = array();
        $SelectColumns = array();
        foreach ($ExportStructure as $ColumnName => $Type) {
            $InsertColumns[] = '`' . $ColumnName . '`';
            if (isset($Mappings[$ColumnName])) {
                $SelectColumns[$ColumnName] = $Mappings[$ColumnName];
            } else {
                $SelectColumns[$ColumnName] = $ColumnName;
            }
        }

        $Query = ReplaceSelect($Query, $SelectColumns);

        $InsertSql = "replace {$DestDb}{$this->DestPrefix}$TableName"
            . " (\n  " . implode(",\n   ", $InsertColumns) . "\n)\n"
            . $Query;

        $this->Query($InsertSql);
    }

    /**
     *
     *
     * @see _ExportTableDB()
     * @param $TableName
     * @param $Query
     * @param array $Mappings
     */
    protected function _ExportTableDBChunked($TableName, $Query, $Mappings = array()) {
        // Grab the table name from the first from.
        if (preg_match('`\sfrom\s([^\s]+)`', $Query, $Matches)) {
            $From = $Matches[1];
        } else {
            trigger_error("Could not figure out table for $TableName chunking.", E_USER_WARNING);

            return;
        }

        $Sql = "show table status like '{$From}';";
        $R = $this->Query($Sql, true);
        $Row = mysql_fetch_assoc($R);
        mysql_free_result($R);
        $Max = $Row['Auto_increment'];

        if (!$Max) {
            $Max = 2000000;
        }

        for ($i = 0; $i < $Max; $i += $this->ChunkSize) {
            $From = $i;
            $To = $From + $this->ChunkSize - 1;

            $Sql = str_replace(array('{from}', '{to}'), array($From, $To), $Query);
            $this->_ExportTableDB($TableName, $Sql, $Mappings);
        }
    }

    /**
     *
     *
     * @param $Columns
     * @return array
     */
    public function FixPermissionColumns($Columns) {
        $Result = array();
        foreach ($Columns as $Index => $Value) {
            if (is_string($Value) && strpos($Value, '.') !== false) {
                $Value = array('Column' => $Value, 'Type' => 'tinyint(1)');
            }
            $Result[$Index] = $Value;
        }

        return $Result;
    }

    /**
     *
     *
     * @param $Mappings
     * @return array
     */
    public function FlipMappings($Mappings) {
        $Result = array();
        foreach ($Mappings as $Column => $Mapping) {
            if (is_string($Mapping)) {
                $Result[$Mapping] = array('Column' => $Column);
            } else {
                $Col = $Mapping['Column'];
                $Mapping['Column'] = $Column;
                $Result[$Col] = $Mapping;
            }
        }

        return $Result;
    }

    /**
     * For outputting how long the export took.
     *
     * @param int $Start
     * @param int $End
     * @return string
     */
    static function FormatElapsed($Start, $End = null) {
        if ($End === null) {
            $Elapsed = $Start;
        } else {
            $Elapsed = $End - $Start;
        }

        $m = floor($Elapsed / 60);
        $s = $Elapsed - $m * 60;
        $Result = sprintf('%02d:%05.2f', $m, $s);

        return $Result;
    }

    /**
     *
     *
     * @param $Value
     * @return int|mixed|string
     */
    static function FormatValue($Value) {
        // Format the value for writing.
        if (is_null($Value)) {
            $Value = self::NULL;
        } elseif (is_numeric($Value)) {
            // Do nothing, formats as is.
        } elseif (is_string($Value)) {
            if (self::$Mb && mb_detect_encoding($Value) != 'UTF-8') {
                $Value = utf8_encode($Value);
            }

            $Value = str_replace(array("\r\n", "\r"), array(self::NEWLINE, self::NEWLINE), $Value);
            $Value = self::QUOTE
                . str_replace(self::$EscapeSearch, self::$EscapeReplace, $Value)
                . self::QUOTE;
        } elseif (is_bool($Value)) {
            $Value = $Value ? 1 : 0;
        } else {
            // Unknown format.
            $Value = self::NULL;
        }

        return $Value;
    }

    /**
     * Execute an sql statement and return the result.
     *
     * @param type $Sql
     * @param type $IndexColumn
     * @return type
     */
    public function Get($Sql, $IndexColumn = false) {
        $R = $this->_Query($Sql, true);
        $Result = array();

        while ($Row = mysql_fetch_assoc($R)) {
            if ($IndexColumn) {
                $Result[$Row[$IndexColumn]] = $Row;
            } else {
                $Result[] = $Row;
            }
        }

        return $Result;
    }

    /**
     * Determine the character set of the origin database.
     *
     * @param string $Table
     * @return string|bool Character set name or false.
     */
    public function GetCharacterSet($Table) {
        // First get the collation for the database.
        $Data = $this->Query("show table status like ':_{$Table}';");
        if (!$Data) {
            return false;
        }
        if ($StatusRow = mysql_fetch_assoc($Data)) {
            $Collation = $StatusRow['Collation'];
        } else {
            return false;
        }

        // Grab the character set from the database.
        $Data = $this->Query("show collation like '$Collation'");
        if (!$Data) {
            return false;
        }
        if ($CollationRow = mysql_fetch_assoc($Data)) {
            $CharacterSet = $CollationRow['Charset'];
            if (!defined('PORTER_CHARACTER_SET')) {
                define('PORTER_CHARACTER_SET', $CharacterSet);
            }

            return $CharacterSet;
        }

        return false;
    }

    /**
     *
     *
     * @return array
     */
    public function GetDatabasePrefixes() {
        // Grab all of the tables.
        $Data = $this->Query('show tables');
        if ($Data === false) {
            return array();
        }

        // Get the names in an array for easier parsing.
        $Tables = array();
        while (($Row = mysql_fetch_array($Data, MYSQL_NUM)) !== false) {
            $Tables[] = $Row[0];
        }
        sort($Tables);

        $Prefixes = array();

        // Loop through each table and get its prefixes.
        foreach ($Tables as $Table) {
            $PxFound = false;
            foreach ($Prefixes as $PxIndex => $Px) {
                $NewPx = $this->_GetPrefix($Table, $Px);
                if (strlen($NewPx) > 0) {
                    $PxFound = true;
                    if ($NewPx != $Px) {
                        $Prefixes[$PxIndex] = $NewPx;
                    }
                    break;
                }
            }
            if (!$PxFound) {
                $Prefixes[] = $Table;
            }
        }

        return $Prefixes;
    }

    /**
     *
     *
     * @param $A
     * @param $B
     * @return string
     */
    protected function _GetPrefix($A, $B) {
        $Length = min(strlen($A), strlen($B));
        $Prefix = '';

        for ($i = 0; $i < $Length; $i++) {
            if ($A[$i] == $B[$i]) {
                $Prefix .= $A[$i];
            } else {
                break;
            }
        }

        return $Prefix;
    }

    /**
     *
     *
     * @param $Row
     * @param $TableOrStructure
     * @param $Mappings
     * @param string $TableName
     * @return array
     */
    public function GetExportStructure($Row, $TableOrStructure, &$Mappings, $TableName = '_') {
        $ExportStructure = array();

        if (is_string($TableOrStructure)) {
            $Structure = $this->_Structures[$TableOrStructure];
        } else {
            $Structure = $TableOrStructure;
        }

        // See what columns to add to the end of the structure.
        foreach ($Row as $Column => $X) {
            if (array_key_exists($Column, $Mappings)) {
                $Mapping = $Mappings[$Column];
                if (is_string($Mapping)) {
                    if (array_key_exists($Mapping, $Structure)) {
                        // This an existing column.
                        $DestColumn = $Mapping;
                        $DestType = $Structure[$DestColumn];
                    } else {
                        // This is a created column.
                        $DestColumn = $Column;
                        $DestType = $Mapping;
                    }
                } elseif (is_array($Mapping)) {
                    if (!isset($Mapping['Column'])) {
                        trigger_error("Mapping for $Column does not have a 'Column' defined.", E_USER_ERROR);
                    }

                    $DestColumn = $Mapping['Column'];

                    if (isset($Mapping['Type'])) {
                        $DestType = $Mapping['Type'];
                    } elseif (isset($Structure[$DestColumn])) {
                        $DestType = $Structure[$DestColumn];
                    } else {
                        $DestType = 'varchar(255)';
                    }
//               $Mappings[$Column] = $DestColumn;
                }
            } elseif (array_key_exists($Column, $Structure)) {
                $DestColumn = $Column;
                $DestType = $Structure[$Column];

                // Verify column doesn't exist in Mapping array's Column element
                $MappingExists = false;
                foreach ($Mappings as $TestMapping) {
                    if ($TestMapping == $Column) {
                        $MappingExists = true;
                    } elseif (is_array($TestMapping) && array_key_exists('Column',
                            $TestMapping) && ($TestMapping['Column'] == $Column)
                    ) {
                        $MappingExists = true;
                    }
                }

                // Also add the column to the mapping.
                if (!$MappingExists) {
                    $Mappings[$Column] = $DestColumn;
                }
            } else {
                $DestColumn = '';
                $DestType = '';
            }

            // Check to see if we have to add the column to the export structure.
            if ($DestColumn && !array_key_exists($DestColumn, $ExportStructure)) {
                // TODO: Make sure $DestType is a valid MySQL type.
                $ExportStructure[$DestColumn] = $DestType;
            }
        }

        // Add filtered mappings since filters can add new columns.
        foreach ($Mappings as $Source => $Options) {
            if (!is_array($Options)) {
                // Force the mappings into the expanded array syntax for easier processing later.
                $Mappings[$Source] = array('Column' => $Options);
                continue;
            }

            if (!isset($Options['Column'])) {
                trigger_error("No column for $TableName(source).$Source.", E_USER_NOTICE);
                continue;
            }

            $DestColumn = $Options['Column'];

            if (!array_key_exists($Source, $Row) && !isset($Options['Type'])) {
                trigger_error("No column for $TableName(source).$Source.", E_USER_NOTICE);
            }

            if (isset($ExportStructure[$DestColumn])) {
                continue;
            }

            if (isset($Structure[$DestColumn])) {
                $DestType = $Structure[$DestColumn];
            } elseif (isset($Options['Type'])) {
                $DestType = $Options['Type'];
            } else {
                trigger_error("No column for $TableName.$DestColumn.", E_USER_NOTICE);
                continue;
            }

            $ExportStructure[$DestColumn] = $DestType;
            $Mappings[$Source] = $DestColumn;
        }

        return $ExportStructure;
    }

    /**
     *
     *
     * @param $Query
     * @param bool $Key
     * @return array
     */
    public function GetQueryStructure($Query, $Key = false) {
        $QueryStruct = rtrim($Query, ';') . ' limit 1';
        if (!$Key) {
            $Key = md5($QueryStruct);
        }
        if (isset($this->_QueryStructures[$Key])) {
            return $this->_QueryStructures[$Key];
        }

        $R = $this->Query($QueryStruct, true);
        $i = 0;
        $Result = array();
        while ($i < mysql_num_fields($R)) {
            $Meta = mysql_fetch_field($R, $i);
            $Result[$Meta->name] = $Meta->table;
            $i++;
        }
        $this->_QueryStructures[$Key] = $Result;

        return $Result;
    }

    /**
     *
     *
     * @param $Sql
     * @param $Default
     * @return mixed
     */
    public function GetValue($Sql, $Default) {
        $Data = $this->Get($Sql);
        if (count($Data) > 0) {
            $Data = array_shift($Data); // first row
            $Result = array_shift($Data); // first column

            return $Result;
        } else {
            return $Default;
        }
    }

    /**
     *
     *
     * @param $Structure
     * @param $GlobalStructure
     * @return string
     */
    protected function _GetTableHeader($Structure, $GlobalStructure) {
        $TableHeader = '';

        foreach ($Structure as $Column => $Type) {
            if (strlen($TableHeader) > 0) {
                $TableHeader .= self::DELIM;
            }
            if (array_key_exists($Column, $GlobalStructure)) {
                $TableHeader .= $Column;
            } else {
                $TableHeader .= $Column . ':' . $Type;
            }
        }

        return $TableHeader;
    }

    /**
     * Are there any filters set on this table?
     *
     * @param $Mappings
     * @return bool
     */
    public function HasFilter(&$Mappings) {
        foreach ($Mappings as $Column => $Info) {
            if (is_array($Info) && isset($Info['Filter'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Do standard HTML decoding in SQL to speed things up.
     *
     * @param string $TableName
     * @param string $ColumnName
     * @param string $PK
     */
    public function HTMLDecoderDb($TableName, $ColumnName, $PK) {
        $Common = array('&amp;' => '&', '&lt;' => '<', '&gt;' => '>', '&apos;' => "'", '&quot;' => '"', '&#39;' => "'");
        foreach ($Common as $From => $To) {
            $FromQ = mysql_escape_string($From);
            $ToQ = mysql_escape_string($To);
            $Sql = "update :_{$TableName} set $ColumnName = replace($ColumnName, '$FromQ', '$ToQ') where $ColumnName like '%$FromQ%'";

            $this->Query($Sql);
        }

        // Now decode the remaining rows.
        $Sql = "select * from :_$TableName where $ColumnName like '%&%;%'";
        $Result = $this->Query($Sql, true);
        while ($Row = mysql_fetch_assoc($Result)) {
            $From = $Row[$ColumnName];
            $To = HTMLDecoder($From);

            if ($From != $To) {
                $ToQ = mysql_escape_string($To);
                $Sql = "update :_{$TableName} set $ColumnName = '$ToQ' where $PK = {$Row[$PK]}";
                $this->Query($Sql, true);
            }
        }
    }

    /**
     * Determine if an index exists in a table
     *
     * @param $IndexName Name of the index to verify
     * @param $Table Name of the table the target index exists in
     * @return bool True if index exists, false otherwise
     */
    public function IndexExists($IndexName, $Table) {
        $IndexName = mysql_real_escape_string($IndexName);
        $Table = mysql_real_escape_string($Table);

        $Result = $this->Query("show index from `{$Table}` WHERE Key_name = '{$IndexName}'", true);

        return $Result && mysql_num_rows($Result);
    }

    /**
     *
     *
     * @return resource
     */
    protected function _OpenFile() {
//      if($this->UseStreaming) {
//         /** Setup the output to stream the file. */
//
//         // required for IE, otherwise Content-Disposition may be ignored
//         if(ini_get('zlib.output_compression'))
//            ini_set('zlib.output_compression', 'Off');
//
//         @ob_end_clean();
//
//
//         $fp = fopen('php://output', 'ab');
//         header("Content-Disposition: attachment; filename=\"{$this->Path}\"");
//         header('Content-Type: text/plain');
//         header("Content-Transfer-Encoding: binary");
//         header('Accept-Ranges: bytes');
//         header("Cache-control: private");
//         header('Pragma: private');
//         header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
//      } else {
        $this->Path = str_replace(' ', '_', $this->Path);
        if ($this->UseCompression()) {
            $fp = gzopen($this->Path, 'wb');
        } else {
            $fp = fopen($this->Path, 'wb');
        }
//      }
        $this->File = $fp;

        return $fp;
    }

    /**
     * Execute a SQL query on the current connection.
     *
     * Wrapper for _Query().
     *
     * @param string $Query The sql to execute.
     * @return resource The query cursor.
     */
    public function Query($Query, $Buffer = false) {
        if (!preg_match('`limit 1;$`', $Query)) {
            $this->Queries[] = $Query;
        }

        if ($this->Destination == 'database' && $this->CaptureOnly) {
            if (!preg_match('`^\s*select|show|describe|create`', $Query)) {
                return 'SKIPPED';
            }
        }

        return $this->_Query($Query, $Buffer);
    }

    /**
     * Execute a SQL query on the current connection.
     *
     * @see Query()
     * @param $Sql
     * @param bool $Buffer
     * @return resource
     */
    protected function _Query($Sql, $Buffer = false) {
        if (isset($this->_LastResult) && is_resource($this->_LastResult)) {
            mysql_free_result($this->_LastResult);
        }
        $Sql = str_replace(':_', $this->Prefix, $Sql); // replace prefix.
        if ($this->SourcePrefix) {
            $Sql = preg_replace("`\b{$this->SourcePrefix}`", $this->Prefix, $Sql); // replace prefix.
        }

        $Sql = rtrim($Sql, ';') . ';';

        $Connection = @mysql_connect($this->_Host, $this->_Username, $this->_Password);
        mysql_select_db($this->_DbName);
        mysql_query("set names {$this->CharacterSet}");

        if ($Buffer) {
            $Result = mysql_query($Sql, $Connection);
        } else {
            $Result = mysql_unbuffered_query($Sql, $Connection);
            if (is_resource($Result)) {
                $this->_LastResult = $Result;
            }
        }

        if ($Result === false) {
            echo '<pre>',
            htmlspecialchars($Sql),
            htmlspecialchars(mysql_error($Connection)),
            '</pre>';
            trigger_error(mysql_error($Connection));
        }

        return $Result;
    }

    /**
     * Send multiple SQL queries.
     *
     * @param string|array $SqlList An array of single query strings or a string of queries terminated with semi-colons.
     */
    public function QueryN($SqlList) {
        if (!is_array($SqlList)) {
            $SqlList = explode(';', $SqlList);
        }

        foreach ($SqlList as $Sql) {
            $Sql = trim($Sql);
            if ($Sql) {
                $this->Query($Sql);
            }
        }
    }

    /**
     * Set database connection details.
     *
     * @param null $Host
     * @param null $Username
     * @param null $Password
     * @param null $DbName
     */
    public function SetConnection($Host = null, $Username = null, $Password = null, $DbName = null) {
        $this->_Host = $Host;
        $this->_Username = $Username;
        $this->_Password = $Password;
        $this->_DbName = $DbName;
    }

    /**
     * Echo a status message to the console.
     *
     * @param $Msg
     */
    public function Status($Msg) {
        if (defined('CONSOLE')) {
            echo $Msg;
        }
    }

    /**
     * Returns an array of all the expected export tables and expected columns in the exports.
     *
     * When exporting tables using ExportTable() all of the columns in this structure will always be exported
     * in the order here, regardless of how their order in the query.
     *
     * @return array
     * @see vnExport::ExportTable()
     */
    public function Structures() {
        return $this->_Structures;
    }

    /**
     * Whether or not to use compression on the output file.
     *
     * @param bool $Value The value to set or NULL to just return the value.
     * @return bool
     */
    public function UseCompression($Value = null) {
        if ($Value !== null) {
            $this->_UseCompression = $Value;
        }

        return $this->_UseCompression && $this->Destination == 'file' && !$this->UseStreaming && function_exists('gzopen');
    }

    /**
     * Returns the version of export file that will be created with this export.
     * The version is used when importing to determine the format of this file.
     *
     * @return string
     */
    public function Version() {
        return APPLICATION_VERSION;
    }

    /**
     * Checks whether or not a table and columns exist in the database.
     *
     * @param string $Table The name of the table to check.
     * @param array $Columns An array of column names to check.
     * @return bool|array The method will return one of the following
     *  - true: If table and all of the columns exist.
     *  - false: If the table does not exist.
     *  - array: The names of the missing columns if one or more columns don't exist.
     */
    public function Exists($Table, $Columns = array()) {
        static $_Exists = array();

        if (!isset($_Exists[$Table])) {
            $Result = $this->Query("show table status like ':_$Table'", true);
            if (!$Result) {
                $_Exists[$Table] = false;
            } elseif (!mysql_fetch_assoc($Result)) {
                $_Exists[$Table] = false;
            } else {
                mysql_free_result($Result);
                $Desc = $this->Query('describe :_' . $Table);
                if ($Desc === false) {
                    $_Exists[$Table] = false;
                } else {
                    if (is_string($Desc)) {
                        die($Desc);
                    }

                    $Cols = array();
                    while (($TD = mysql_fetch_assoc($Desc)) !== false) {
                        $Cols[$TD['Field']] = $TD;
                    }
                    mysql_free_result($Desc);
                    $_Exists[$Table] = $Cols;
                }
            }
        }

        if ($_Exists[$Table] == false) {
            return false;
        }

        $Columns = (array)$Columns;

        if (count($Columns) == 0) {
            return true;
        }

        $Missing = array();
        $Cols = array_keys($_Exists[$Table]);
        foreach ($Columns as $Column) {
            if (!in_array($Column, $Cols)) {
                $Missing[] = $Column;
            }
        }

        return count($Missing) == 0 ? true : $Missing;
    }

    /**
     * Checks all required source tables are present.
     *
     * @param array $RequiredTables
     * @return array|string
     */
    public function VerifySource($RequiredTables) {
        $MissingTables = false;
        $CountMissingTables = 0;
        $MissingColumns = array();

        foreach ($RequiredTables as $ReqTable => $ReqColumns) {
            $TableDescriptions = $this->Query('describe :_' . $ReqTable);
            //echo 'describe '.$Prefix.$ReqTable;
            if ($TableDescriptions === false) { // Table doesn't exist
                $CountMissingTables++;
                if ($MissingTables !== false) {
                    $MissingTables .= ', ' . $ReqTable;
                } else {
                    $MissingTables = $ReqTable;
                }
            } else {
                // Build array of columns in this table
                $PresentColumns = array();
                while (($TD = mysql_fetch_assoc($TableDescriptions)) !== false) {
                    $PresentColumns[] = $TD['Field'];
                }
                // Compare with required columns
                foreach ($ReqColumns as $ReqCol) {
                    if (!in_array($ReqCol, $PresentColumns)) {
                        $MissingColumns[$ReqTable][] = $ReqCol;
                    }
                }

                mysql_free_result($TableDescriptions);
            }
        }

        // Return results
        if ($MissingTables === false) {
            if (count($MissingColumns) > 0) {
                $Result = array();

                // Build a string of missing columns.
                foreach ($MissingColumns as $Table => $Columns) {
                    $Result[] = "The $Table table is missing the following column(s): " . implode(', ', $Columns);
                }

                return implode("<br />\n", $Result);
            } else {
                return true;
            } // Nothing missing!
        } elseif ($CountMissingTables == count($RequiredTables)) {
            $Result = 'The required tables are not present in the database. Make sure you entered the correct database name and prefix and try again.';

            // Guess the prefixes to notify the user.
            $Prefixes = $this->GetDatabasePrefixes();
            if (count($Prefixes) == 1) {
                $Result .= ' Based on the database you provided, your database prefix is probably ' . implode(', ',
                        $Prefixes);
            } elseif (count($Prefixes) > 0) {
                $Result .= ' Based on the database you provided, your database prefix is probably one of the following: ' . implode(', ',
                        $Prefixes);
            }

            return $Result;
        } else {
            return 'Missing required database tables: ' . $MissingTables;
        }
    }

    /**
     * Start table write to file.
     *
     * @param $fp
     * @param $TableName
     * @param $ExportStructure
     */
    public function WriteBeginTable($fp, $TableName, $ExportStructure) {
        $TableHeader = '';

        foreach ($ExportStructure as $Key => $Value) {
            if (is_numeric($Key)) {
                $Column = $Value;
                $Type = '';
            } else {
                $Column = $Key;
                $Type = $Value;
            }

            if (strlen($TableHeader) > 0) {
                $TableHeader .= self::DELIM;
            }

            if ($Type) {
                $TableHeader .= $Column . ':' . $Type;
            } else {
                $TableHeader .= $Column;
            }
        }

        fwrite($fp, 'Table: ' . $TableName . self::NEWLINE);
        fwrite($fp, $TableHeader . self::NEWLINE);
    }

    /**
     * End table write to file.
     *
     * @param $fp
     */
    public function WriteEndTable($fp) {
        fwrite($fp, self::NEWLINE);
    }

    /**
     * Write a table's row to file.
     *
     * @param $fp
     * @param $Row
     * @param $ExportStructure
     * @param $RevMappings
     */
    public function WriteRow($fp, $Row, $ExportStructure, $RevMappings) {
        $this->CurrentRow =& $Row;

        // Loop through the columns in the export structure and grab their values from the row.
        $ExRow = array();
        foreach ($ExportStructure as $Field => $Type) {
            // Get the value of the export.
            $Value = null;
            if (isset($RevMappings[$Field]) && isset($Row[$RevMappings[$Field]['Column']])) {
                // The column is mapped.
                $Value = $Row[$RevMappings[$Field]['Column']];
            } elseif (array_key_exists($Field, $Row)) {
                // The column has an exact match in the export.
                $Value = $Row[$Field];
            }

            // Check to see if there is a callback filter.
            $Filtered = false;
            if (isset($RevMappings[$Field]['Filter'])) {
                $Callback = $RevMappings[$Field]['Filter'];

                $Row2 =& $Row;
                $Value = call_user_func($Callback, $Value, $Field, $Row2, $Field);
                $Row = $this->CurrentRow;
                $Filtered = true;
            }

            // Format the value for writing.
            if (is_null($Value)) {
                $Value = self::NULL;
            } elseif (is_integer($Value)) {
                // Do nothing, formats as is.
                // Only allow ints because PHP allows weird shit as numeric like "\n\n.1"
            } elseif (is_string($Value) || is_numeric($Value)) {
                // Check to see if there is a callback filter.
                if (!isset($RevMappings[$Field])) {
                    //$Value = call_user_func($Filters[$Field], $Value, $Field, $Row);
                } else {
                    if (self::$Mb && mb_detect_encoding($Value) != 'UTF-8') {
                        $Value = utf8_encode($Value);
                    }
                }

                $Value = str_replace(array("\r\n", "\r"), array(self::NEWLINE, self::NEWLINE), $Value);
                $Value = self::QUOTE
                    . str_replace(self::$EscapeSearch, self::$EscapeReplace, $Value)
                    . self::QUOTE;
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

    /**
     * SQL to get the file extension from a string.
     *
     * @param string $ColumnName
     * @return string SQL.
     */
    public static function FileExtension($ColumnName) {
        return "right($ColumnName, instr(reverse($ColumnName), '.'))";
    }
}

?>
