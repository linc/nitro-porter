<?php
/**
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Object for exporting other database structures into a format that can be imported.
 */
class ExportModel {

    /** Character constants. */
    const COMMENT = '//';
    const DELIM = ',';
    const ESCAPE = '\\';
    const NEWLINE = "\n";
    const NULL = '\N';
    const QUOTE = '"';

    /** @var bool */
    public $captureOnly = false;

    /** @var array Any comments that have been written during the export. */
    public $comments = array();

    /** @var ExportController * */
    public $controller = null;

    /** @var string The charcter set to set as the connection anytime the database connects. */
    public $characterSet = 'utf8';

    /** @var int The chunk size when exporting large tables. */
    public $chunkSize = 100000;

    /** @var array * */
    public $currentRow = null;

    /** @var string Where we are sending this export: 'file' or 'database'. * */
    public $destination = 'file';

    /** @var string * */
    public $destPrefix = 'GDN_z';

    /** @var array * */
    public static $escapeSearch = array();

    /** @var array * */
    public static $escapeReplace = array();

    /** @var object File pointer */
    public $file = null;

    /** @var string A prefix to put into an automatically generated filename. */
    public $filenamePrefix = '';

    /** @var string Database host. * */
    public $_host = 'localhost';

    /** @var bool Whether mb_detect_encoding() is available. * */
    public static $mb = false;

    /** @var object PDO instance */
    protected $_PDO = null;

    /** @var string Database password. * */
    protected $_password;

    /** @var string The path to the export file. */
    public $path = '';

    /**
     * @var string The database prefix. When you pass a sql string to ExportTable() it will replace occurances of :_ with this property.
     * @see ExportModel::ExportTable()
     */
    public $prefix = '';

    /** @var array * */
    public $queries = array();

    /** @var array * */
    protected $_queryStructures = array();

    /** @var array Tables to limit the export to.  A full export is an empty array. */
    public $restrictedTables = array();

    /** @var string The path to the source of the export in the case where a file is being converted. */
    public $sourcePath = '';

    /** @var string */
    public $sourcePrefix = '';

    /** @var bool * */
    public $scriptCreateTable = true;

    /** @var array Structures that define the format of the export tables. */
    protected $_structures = array();

    /** @var bool Whether to limit results to the $testLimit. */
    public $testMode = false;

    /** @var int How many records to limit when $testMode is enabled. */
    public $testLimit = 10;

    /** @var bool Whether or not to use compression when creating the file. */
    protected $_useCompression = true;

    /** @var string Database username. */
    protected $_username;

    /**
     * Setup.
     */
    public function __construct() {
        self::$mb = function_exists('mb_detect_encoding');

        // Set the search and replace to escape strings.
        self::$escapeSearch = array(self::ESCAPE, self::DELIM, self::NEWLINE, self::QUOTE); // escape must go first
        self::$escapeReplace = array(
            self::ESCAPE . self::ESCAPE,
            self::ESCAPE . self::DELIM,
            self::ESCAPE . self::NEWLINE,
            self::ESCAPE . self::QUOTE
        );

        // Load structure.
        $this->_structures = vanillaStructure();
    }

    /**
     * Create the export file and begin the export.
     *
     * @param string $path The path to the export file.
     * @param string $source The source program that created the export. This may be used by the import routine to do additional processing.
     * @param array $header
     * @return resource Pointer to the file created.
     */
    public function beginExport($path = '', $source = '', $header = array()) {
        $this->comments = array();
        $this->beginTime = microtime(true);

        // Allow us to define where the output file goes.
        if ($path) {
            $this->path = $path;
        } elseif ($this->controller->param('destpath')) {
            $this->path = $this->controller->param('destpath');
            if (strstr($this->path, '/') !== false && substr($this->path, 1, -1) != '/') {
                // We're using slash paths but didn't include a final slash.
                $this->path .= '/';
            }
        }

        // Allow the $path parameter to override this default naming.
        if (!$path) {
            $this->path .= 'export_' . ($this->filenamePrefix ? $this->filenamePrefix . '_' : '') . date('Y-m-d_His') . '.txt' . ($this->useCompression() ? '.gz' : '');
        }

        // Start the file pointer.
        $fp = $this->_openFile();

        // Build meta info about where this file came from.
        $comment = 'Vanilla Export: ' . $this->version();
        if ($source) {
            $comment .= self::DELIM . ' Source: ' . $source;
        }
        foreach ($header as $key => $value) {
            $comment .= self::DELIM . " $key: $value";
        }

        // Add meta info to the output.
        if ($this->captureOnly) {
            $this->comment($comment);
        } else {
            fwrite($fp, $comment . self::NEWLINE . self::NEWLINE);
        }

        $this->comment('Export Started: ' . date('Y-m-d H:i:s'));

        return $fp;
    }

    /**
     * Write a comment to the export file.
     *
     * @param string $message The message to write.
     * @param bool $echo Whether or not to echo the message in addition to writing it to the file.
     */
    public function comment($message, $echo = true) {
        if ($this->destination == 'file') {
            $char = self::COMMENT;
        } else {
            $char = '--';
        }

        $comment = $char . ' ' . str_replace(self::NEWLINE, self::NEWLINE . self::COMMENT . ' ',
                $message) . self::NEWLINE;

        fwrite($this->file, $comment);
        if ($echo) {
            if (defined('CONSOLE')) {
                echo $comment;
            } else {
                $this->comments[] = $message;
            }
        }
    }

    /**
     * End the export and close the export file.
     *
     * This method must be called if BeginExport() has been called or else the export file will not be closed.
     */
    public function endExport() {
        $this->endTime = microtime(true);
        $this->totalTime = $this->endTime - $this->beginTime;

        $this->comment($this->path);
        $this->comment('Export Completed: ' . date('Y-m-d H:i:s'));
        $this->comment(sprintf('Elapsed Time: %s', self::formatElapsed($this->totalTime)));

        if ($this->testMode || $this->controller->param('dumpsql') || $this->captureOnly) {
            $queries = implode("\n\n", $this->queries);
            if ($this->destination == 'database') {
                fwrite($this->file, $queries);
            } else {
                $this->comment($queries, true);
            }
        }

        if ($this->useCompression() && function_exists('gzopen')) {
            gzclose($this->file);
        } else {
            fclose($this->file);
        }
    }

    /**
     * Export a table to the export file.
     *
     * @param string $tableName the name of the table to export. This must correspond to one of the accepted Vanilla tables.
     * @param mixed $query The query that will fetch the data for the export this can be one of the following:
     *  - <b>String</b>: Represents a string of SQL to execute.
     *  - <b>PDOStatement</b>: Represents an already executed query result set.
     *  - <b>Array</b>: Represents an array of associative arrays or objects containing the data in the export.
     * @param array $mappings Specifies mappings, if any, between the source and the export where the keys represent the source columns and the values represent Vanilla columns.
     *      - If you specify a Vanilla column then it must be in the export structure contained in this class.
     *   - If you specify a MySQL type then the column will be added.
     *   - If you specify an array you can have the following keys: Column, and Type where Column represents the new column name and Type represents the MySQL type.
     *  For a list of the export tables and columns see $this->Structure().
     */
    public function exportTable($tableName, $query, $mappings = array()) {
        if (!empty($this->restrictedTables) && !in_array(strtolower($tableName), $this->restrictedTables)) {
            $this->comment("Skipping table: $tableName");
        } else {
            $beginTime = microtime(true);

            $rowCount = $this->_exportTable($tableName, $query, $mappings);

            $endTime = microtime(true);
            $elapsed = self::formatElapsed($beginTime, $endTime);
            $this->comment("Exported Table: $tableName ($rowCount rows, $elapsed)");
            fwrite($this->file, self::NEWLINE);
        }
    }

    /**
     *
     *
     * @param $tableName
     * @param $query
     * @param array $mappings
     */
    protected function _exportTableImport($tableName, $query, $mappings = array()) {
        // Backup the settings.
        $destinationBak = $this->destination;
        $this->destination = 'file';

        $_fileBak = $this->file;
        $path = dirname(__FILE__) . '/' . $tableName . '.csv';
        $this->comment("Exporting To: $path");
        $fp = fopen($path, 'wb');
        $this->file = $fp;

        // First export the file to a file.
        $this->_exportTable($tableName, $query, $mappings, array('NoEndline' => true));

        // Now define a table to import into.
        $this->_createExportTable($tableName, $query, $mappings);

        // Now load the data.
        $sql = "load data local infile '$path' into table {$this->destDb}.{$this->destPrefix}$tableName
         character set utf8
         columns terminated by ','
         optionally enclosed by '\"'
         escaped by '\\\\'
         lines terminated by '\\n'
         ignore 2 lines";
        $this->query($sql);

        // Restore the settings.
        $this->destination = $destinationBak;
        $this->file = $_fileBak;
    }

    /**
     * Convert database blobs into files.
     *
     * @param $sql
     * @param $blobColumn
     * @param $pathColumn
     * @param bool $thumbnail
     */
    public function exportBlobs($sql, $blobColumn, $pathColumn, $thumbnail = false) {
        $this->comment('Exporting blobs...');

        $result = $this->query($sql);
        $count = 0;
        while ($row = mysql_fetch_assoc($result)) {
            // vBulletin attachment hack (can't do this in MySQL)
            if (strpos($row[$pathColumn], '.attach') && strpos($row[$pathColumn], 'attachments/') !== false) {
                $pathParts = explode('/', $row[$pathColumn]); // 3 parts

                // Split up the userid into a path, digit by digit
                $n = strlen($pathParts[1]);
                $dirParts = array();
                for ($i = 0; $i < $n; $i++) {
                    $dirParts[] = $pathParts[1]{$i};
                }
                $pathParts[1] = implode('/', $dirParts);

                // Rebuild full path
                $row[$pathColumn] = implode('/', $pathParts);
            }

            $path = $row[$pathColumn];

            // Build path
            if (!file_exists(dirname($path))) {
                $r = mkdir(dirname($path), 0777, true);
                if (!$r) {
                    die("Could not create " . dirname($path));
                }
            }

            if ($thumbnail) {
                $picPath = str_replace('/avat', '/pavat', $path);
                $fp = fopen($picPath, 'wb');
            } else {
                $fp = fopen($path, 'wb');
            }
            if (!is_resource($fp)) {
                die("Could not open $path.");
            }

            fwrite($fp, $row[$blobColumn]);
            fclose($fp);
            $this->status('.');

            if ($thumbnail) {
                if ($thumbnail === true) {
                    $thumbnail = 50;
                }

                $thumbPath = str_replace('/avat', '/navat', $path);
                generateThumbnail($picPath, $thumbPath, $thumbnail, $thumbnail);
            }
            $count++;
        }
        $this->status("$count Blobs.\n");
        $this->comment("$count Blobs.", false);
    }

    /**
     * Process for writing an entire single table to file.
     *
     * @see ExportTable()
     * @param $tableName
     * @param $query
     * @param array $mappings
     * @param array $options
     * @return int
     */
    protected function _exportTable($tableName, $query, $mappings = array(), $options = array()) {
        $fp = $this->file;

        // Make sure the table is valid for export.
        if (!array_key_exists($tableName, $this->_structures)) {
            $this->comment("Error: $tableName is not a valid export."
                . " The valid tables for export are " . implode(", ", array_keys($this->_structures)));
            fwrite($fp, self::NEWLINE);

            return;
        }

        if ($this->destination == 'database') {
            $this->_exportTableDB($tableName, $query, $mappings);

            return;
        }

        // Check for a chunked query.
        $query = str_replace('{from}', -2000000000, $query);
        $query = str_replace('{to}', 2000000000, $query);

        if (strpos($query, '{from}') !== false) {
            $this->_exportTableDBChunked($tableName, $query, $mappings);

            return;
        }

        // If we are in test mode then limit the query.
        if ($this->testMode && $this->testLimit) {
            $query = rtrim($query, ';');
            if (stripos($query, 'select') !== false && stripos($query, 'limit') === false) {
                $query .= " limit {$this->testLimit}";
            }
        }

        $structure = $this->_structures[$tableName];

        $lastID = 0;
        $IDName = 'NOTSET';
        $firstQuery = true;

        $data = $this->query($query);

        // Loop through the data and write it to the file.
        $rowCount = 0;
        if ($data !== false) {
            while (($row = mysql_fetch_assoc($data)) !== false) {
                $row = (array)$row; // export%202010-05-06%20210937.txt
                $this->currentRow =& $row;
                $rowCount++;

                if ($firstQuery) {
                    // Get the export structure.
                    $exportStructure = $this->getExportStructure($row, $structure, $mappings, $tableName);
                    $revMappings = $this->flipMappings($mappings);
                    $this->writeBeginTable($fp, $tableName, $exportStructure);

                    $firstQuery = false;
                }
                $this->writeRow($fp, $row, $exportStructure, $revMappings);
            }
        }
        if ($data !== false) {
            mysql_free_result($data);
        }
        unset($data);

        if (!isset($options['NoEndline'])) {
            $this->writeEndTable($fp);
        }

        mysql_close();

        return $rowCount;
    }

    /**
     *
     *
     * @param $tableName
     * @param $query
     * @param array $mappings
     */
    protected function _createExportTable($tableName, $query, $mappings = array()) {
        if (!$this->scriptCreateTable) {
            return;
        }

        // Limit the query to grab any additional columns.
        $queryStruct = rtrim($query, ';') . ' limit 1';
        $structure = $this->_structures[$tableName];

        $data = $this->query($queryStruct, true);
//      $mb = function_exists('mb_detect_encoding');

        // Loop through the data and write it to the file.
        if ($data === false) {
            return;
        }

        // Get the export structure.
        while (($row = mysql_fetch_assoc($data)) !== false) {
            $row = (array)$row;

            // Get the export structure.
            $exportStructure = $this->getExportStructure($row, $structure, $mappings, $tableName);

            break;
        }
        mysql_close($data);

        // Build the create table statement.
        $columnDefs = array();
        foreach ($exportStructure as $columnName => $type) {
            $columnDefs[] = "`$columnName` $type";
        }
        $destDb = '';
        if (isset($this->destDb)) {
            $destDb = $this->destDb . '.';
        }

        $this->query("drop table if exists {$destDb}{$this->destPrefix}$tableName");
        $createSql = "create table {$destDb}{$this->destPrefix}$tableName (\n  " . implode(",\n  ",
                $columnDefs) . "\n) engine=innodb";

        $this->query($createSql);
    }

    /**
     *
     *
     * @see _exportTable()
     * @param $tableName
     * @param $query
     * @param array $mappings
     */
    protected function _exportTableDB($tableName, $query, $mappings = array()) {
        if ($this->hasFilter($mappings) || strpos($query, 'union all') !== false) {
            $this->_exportTableImport($tableName, $query, $mappings);

            return;
        }

        // Check for a chunked query.
        if (strpos($query, '{from}') !== false) {
            $this->_exportTableDBChunked($tableName, $query, $mappings);

            return;
        }

        $destDb = '';
        if (isset($this->destDb)) {
            $destDb = $this->destDb . '.';
        }

        // Limit the query to grab any additional columns.
        $queryStruct = $this->getQueryStructure($query, $tableName);
        $structure = $this->_structures[$tableName];

        $exportStructure = $this->getExportStructure($queryStruct, $structure, $mappings, $tableName);

        $mappings = $this->flipMappings($mappings);

        // Build the create table statement.
        $columnDefs = array();
        foreach ($exportStructure as $columnName => $type) {
            $columnDefs[] = "`$columnName` $type";
        }
        if ($this->scriptCreateTable) {
            $this->query("drop table if exists {$destDb}{$this->destPrefix}$tableName");
            $createSql = "create table {$destDb}{$this->destPrefix}$tableName (\n  " . implode(",\n  ",
                    $columnDefs) . "\n) engine=innodb";
            $this->query($createSql);
        }

        $query = rtrim($query, ';');
        // Build the insert statement.
        if ($this->testMode && $this->testLimit) {
            $query .= " limit {$this->testLimit}";
        }

        $insertColumns = array();
        $selectColumns = array();
        foreach ($exportStructure as $columnName => $type) {
            $insertColumns[] = '`' . $columnName . '`';
            if (isset($mappings[$columnName])) {
                $selectColumns[$columnName] = $mappings[$columnName];
            } else {
                $selectColumns[$columnName] = $columnName;
            }
        }

        $query = replaceSelect($query, $selectColumns);

        $insertSql = "replace {$destDb}{$this->destPrefix}$tableName"
            . " (\n  " . implode(",\n   ", $insertColumns) . "\n)\n"
            . $query;

        $this->query($insertSql);
    }

    /**
     *
     *
     * @see _exportTableDB()
     * @param $tableName
     * @param $query
     * @param array $mappings
     */
    protected function _exportTableDBChunked($tableName, $query, $mappings = array()) {
        // Grab the table name from the first from.
        if (preg_match('`\sfrom\s([^\s]+)`', $query, $matches)) {
            $from = $matches[1];
        } else {
            trigger_error("Could not figure out table for $tableName chunking.", E_USER_WARNING);

            return;
        }

        $sql = "show table status like '{$from}';";
        $r = $this->query($sql, true);
        $row = mysql_fetch_assoc($r);
        mysql_free_result($r);
        $max = $row['Auto_increment'];

        if (!$max) {
            $max = 2000000;
        }

        for ($i = 0; $i < $max; $i += $this->chunkSize) {
            $from = $i;
            $to = $from + $this->chunkSize - 1;

            $sql = str_replace(array('{from}', '{to}'), array($from, $to), $query);
            $this->_exportTableDB($tableName, $sql, $mappings);
        }
    }

    /**
     *
     *
     * @param $columns
     * @return array
     */
    public function fixPermissionColumns($columns) {
        $result = array();
        foreach ($columns as $index => $value) {
            if (is_string($value) && strpos($value, '.') !== false) {
                $value = array('Column' => $value, 'Type' => 'tinyint(1)');
            }
            $result[$index] = $value;
        }

        return $result;
    }

    /**
     *
     *
     * @param $mappings
     * @return array
     */
    public function flipMappings($mappings) {
        $result = array();
        foreach ($mappings as $column => $mapping) {
            if (is_string($mapping)) {
                $result[$mapping] = array('Column' => $column);
            } else {
                $col = $mapping['Column'];
                $mapping['Column'] = $column;
                $result[$col] = $mapping;
            }
        }

        return $result;
    }

    /**
     * For outputting how long the export took.
     *
     * @param int $start
     * @param int $end
     * @return string
     */
    public static function formatElapsed($start, $end = null) {
        if ($end === null) {
            $elapsed = $start;
        } else {
            $elapsed = $end - $start;
        }

        $m = floor($elapsed / 60);
        $s = $elapsed - $m * 60;
        $result = sprintf('%02d:%05.2f', $m, $s);

        return $result;
    }

    /**
     *
     *
     * @param $value
     * @return int|mixed|string
     */
    public static function formatValue($value) {
        // Format the value for writing.
        if (is_null($value)) {
            $value = self::NULL;
        } elseif (is_numeric($value)) {
            // Do nothing, formats as is.
        } elseif (is_string($value)) {
            if (self::$mb && mb_detect_encoding($value) != 'UTF-8') {
                $value = utf8_encode($value);
            }

            $value = str_replace(array("\r\n", "\r"), array(self::NEWLINE, self::NEWLINE), $value);
            $value = self::QUOTE
                . str_replace(self::$escapeSearch, self::$escapeReplace, $value)
                . self::QUOTE;
        } elseif (is_bool($value)) {
            $value = $value ? 1 : 0;
        } else {
            // Unknown format.
            $value = self::NULL;
        }

        return $value;
    }

    /**
     * Execute an sql statement and return the result.
     *
     * @param type $sql
     * @param type $indexColumn
     * @return type
     */
    public function get($sql, $indexColumn = false) {
        $r = $this->_query($sql, true);
        $result = array();

        while ($row = mysql_fetch_assoc($r)) {
            if ($indexColumn) {
                $result[$row[$indexColumn]] = $row;
            } else {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * Determine the character set of the origin database.
     *
     * @param string $table
     * @return string|bool Character set name or false.
     */
    public function getCharacterSet($table) {
        // First get the collation for the database.
        $data = $this->query("show table status like ':_{$table}';");
        if (!$data) {
            return false;
        }
        if ($statusRow = mysql_fetch_assoc($data)) {
            $collation = $statusRow['Collation'];
        } else {
            return false;
        }

        // Grab the character set from the database.
        $data = $this->query("show collation like '$collation'");
        if (!$data) {
            return false;
        }
        if ($collationRow = mysql_fetch_assoc($data)) {
            $characterSet = $collationRow['Charset'];
            if (!defined('PORTER_CHARACTER_SET')) {
                define('PORTER_CHARACTER_SET', $characterSet);
            }

            return $characterSet;
        }

        return false;
    }

    /**
     *
     *
     * @return array
     */
    public function getDatabasePrefixes() {
        // Grab all of the tables.
        $data = $this->query('show tables');
        if ($data === false) {
            return array();
        }

        // Get the names in an array for easier parsing.
        $tables = array();
        while (($row = mysql_fetch_array($data, MYSQL_NUM)) !== false) {
            $tables[] = $row[0];
        }
        sort($tables);

        $prefixes = array();

        // Loop through each table and get its prefixes.
        foreach ($tables as $table) {
            $pxFound = false;
            foreach ($prefixes as $pxIndex => $px) {
                $newPx = $this->_getPrefix($table, $px);
                if (strlen($newPx) > 0) {
                    $pxFound = true;
                    if ($newPx != $px) {
                        $prefixes[$pxIndex] = $newPx;
                    }
                    break;
                }
            }
            if (!$pxFound) {
                $prefixes[] = $table;
            }
        }

        return $prefixes;
    }

    /**
     *
     *
     * @param $a
     * @param $b
     * @return string
     */
    protected function _getPrefix($a, $b) {
        $length = min(strlen($a), strlen($b));
        $prefix = '';

        for ($i = 0; $i < $length; $i++) {
            if ($a[$i] == $b[$i]) {
                $prefix .= $a[$i];
            } else {
                break;
            }
        }

        return $prefix;
    }

    /**
     *
     *
     * @param $row
     * @param $tableOrStructure
     * @param $mappings
     * @param string $tableName
     * @return array
     */
    public function getExportStructure($row, $tableOrStructure, &$mappings, $tableName = '_') {
        $exportStructure = array();

        if (is_string($tableOrStructure)) {
            $structure = $this->_structures[$tableOrStructure];
        } else {
            $structure = $tableOrStructure;
        }

        // See what columns to add to the end of the structure.
        foreach ($row as $column => $x) {
            if (array_key_exists($column, $mappings)) {
                $mapping = $mappings[$column];
                if (is_string($mapping)) {
                    if (array_key_exists($mapping, $structure)) {
                        // This an existing column.
                        $destColumn = $mapping;
                        $destType = $structure[$destColumn];
                    } else {
                        // This is a created column.
                        $destColumn = $column;
                        $destType = $mapping;
                    }
                } elseif (is_array($mapping)) {
                    if (!isset($mapping['Column'])) {
                        trigger_error("Mapping for $column does not have a 'Column' defined.", E_USER_ERROR);
                    }

                    $destColumn = $mapping['Column'];

                    if (isset($mapping['Type'])) {
                        $destType = $mapping['Type'];
                    } elseif (isset($structure[$destColumn])) {
                        $destType = $structure[$destColumn];
                    } else {
                        $destType = 'varchar(255)';
                    }
//               $mappings[$column] = $destColumn;
                }
            } elseif (array_key_exists($column, $structure)) {
                $destColumn = $column;
                $destType = $structure[$column];

                // Verify column doesn't exist in Mapping array's Column element
                $mappingExists = false;
                foreach ($mappings as $testMapping) {
                    if ($testMapping == $column) {
                        $mappingExists = true;
                    } elseif (is_array($testMapping) && array_key_exists('Column',
                            $testMapping) && ($testMapping['Column'] == $column)
                    ) {
                        $mappingExists = true;
                    }
                }

                // Also add the column to the mapping.
                if (!$mappingExists) {
                    $mappings[$column] = $destColumn;
                }
            } else {
                $destColumn = '';
                $destType = '';
            }

            // Check to see if we have to add the column to the export structure.
            if ($destColumn && !array_key_exists($destColumn, $exportStructure)) {
                // TODO: Make sure $destType is a valid MySQL type.
                $exportStructure[$destColumn] = $destType;
            }
        }

        // Add filtered mappings since filters can add new columns.
        foreach ($mappings as $source => $options) {
            if (!is_array($options)) {
                // Force the mappings into the expanded array syntax for easier processing later.
                $mappings[$source] = array('Column' => $options);
                continue;
            }

            if (!isset($options['Column'])) {
                trigger_error("No column for $tableName(source).$source.", E_USER_NOTICE);
                continue;
            }

            $destColumn = $options['Column'];

            if (!array_key_exists($source, $row) && !isset($options['Type'])) {
                trigger_error("No column for $tableName(source).$source.", E_USER_NOTICE);
            }

            if (isset($exportStructure[$destColumn])) {
                continue;
            }

            if (isset($structure[$destColumn])) {
                $destType = $structure[$destColumn];
            } elseif (isset($options['Type'])) {
                $destType = $options['Type'];
            } else {
                trigger_error("No column for $tableName.$destColumn.", E_USER_NOTICE);
                continue;
            }

            $exportStructure[$destColumn] = $destType;
            $mappings[$source] = $destColumn;
        }

        return $exportStructure;
    }

    /**
     *
     *
     * @param $query
     * @param bool $key
     * @return array
     */
    public function getQueryStructure($query, $key = false) {
        $queryStruct = rtrim($query, ';') . ' limit 1';
        if (!$key) {
            $key = md5($queryStruct);
        }
        if (isset($this->_queryStructures[$key])) {
            return $this->_queryStructures[$key];
        }

        $r = $this->query($queryStruct, true);
        $i = 0;
        $result = array();
        while ($i < mysql_num_fields($r)) {
            $meta = mysql_fetch_field($r, $i);
            $result[$meta->name] = $meta->table;
            $i++;
        }
        $this->_queryStructures[$key] = $result;

        return $result;
    }

    /**
     *
     *
     * @param $sql
     * @param $default
     * @return mixed
     */
    public function getValue($sql, $default) {
        $data = $this->get($sql);
        if (count($data) > 0) {
            $data = array_shift($data); // first row
            $result = array_shift($data); // first column

            return $result;
        } else {
            return $default;
        }
    }

    /**
     *
     *
     * @param $structure
     * @param $globalStructure
     * @return string
     */
    protected function _getTableHeader($structure, $globalStructure) {
        $tableHeader = '';

        foreach ($structure as $column => $type) {
            if (strlen($tableHeader) > 0) {
                $tableHeader .= self::DELIM;
            }
            if (array_key_exists($column, $globalStructure)) {
                $tableHeader .= $column;
            } else {
                $tableHeader .= $column . ':' . $type;
            }
        }

        return $tableHeader;
    }

    /**
     * Are there any filters set on this table?
     *
     * @param $mappings
     * @return bool
     */
    public function hasFilter(&$mappings) {
        foreach ($mappings as $column => $info) {
            if (is_array($info) && isset($info['Filter'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Do standard HTML decoding in SQL to speed things up.
     *
     * @param string $tableName
     * @param string $columnName
     * @param string $PK
     */
    public function HTMLDecoderDb($tableName, $columnName, $PK) {
        $common = array('&amp;' => '&', '&lt;' => '<', '&gt;' => '>', '&apos;' => "'", '&quot;' => '"', '&#39;' => "'");
        foreach ($common as $from => $to) {
            $fromQ = mysql_escape_string($from);
            $toQ = mysql_escape_string($to);
            $sql = "update :_{$tableName} set $columnName = replace($columnName, '$fromQ', '$toQ') where $columnName like '%$fromQ%'";

            $this->query($sql);
        }

        // Now decode the remaining rows.
        $sql = "select * from :_$tableName where $columnName like '%&%;%'";
        $result = $this->query($sql, true);
        while ($row = mysql_fetch_assoc($result)) {
            $from = $row[$columnName];
            $to = HTMLDecoder($from);

            if ($from != $to) {
                $toQ = mysql_escape_string($to);
                $sql = "update :_{$tableName} set $columnName = '$toQ' where $PK = {$row[$PK]}";
                $this->query($sql, true);
            }
        }
    }

    /**
     * Determine if an index exists in a table
     *
     * @param $indexName Name of the index to verify
     * @param $table Name of the table the target index exists in
     * @return bool True if index exists, false otherwise
     */
    public function indexExists($indexName, $table) {
        $indexName = mysql_real_escape_string($indexName);
        $table = mysql_real_escape_string($table);

        $result = $this->query("show index from `{$table}` WHERE Key_name = '{$indexName}'", true);

        return $result && mysql_num_rows($result);
    }

    /**
     *
     *
     * @return resource
     */
    protected function _openFile() {
        $this->path = str_replace(' ', '_', $this->path);
        if ($this->useCompression()) {
            $fp = gzopen($this->path, 'wb');
        } else {
            $fp = fopen($this->path, 'wb');
        }

        $this->file = $fp;

        return $fp;
    }

    /**
     * Execute a SQL query on the current connection.
     *
     * Wrapper for _Query().
     *
     * @param string $query The sql to execute.
     * @return resource The query cursor.
     */
    public function query($query, $buffer = false) {
        if (!preg_match('`limit 1;$`', $query)) {
            $this->queries[] = $query;
        }

        if ($this->destination == 'database' && $this->captureOnly) {
            if (!preg_match('`^\s*select|show|describe|create`', $query)) {
                return 'SKIPPED';
            }
        }

        return $this->_query($query, $buffer);
    }

    /**
     * Execute a SQL query on the current connection.
     *
     * @see Query()
     * @param $sql
     * @param bool $buffer
     * @return resource
     */
    protected function _query($sql, $buffer = false) {
        if (isset($this->_lastResult) && is_resource($this->_lastResult)) {
            mysql_free_result($this->_lastResult);
        }
        $sql = str_replace(':_', $this->prefix, $sql); // replace prefix.
        if ($this->sourcePrefix) {
            $sql = preg_replace("`\b{$this->sourcePrefix}`", $this->prefix, $sql); // replace prefix.
        }

        $sql = rtrim($sql, ';') . ';';

        $connection = @mysql_connect($this->_host, $this->_username, $this->_password);
        mysql_select_db($this->_dbName);
        mysql_query("set names {$this->characterSet}");

        if ($buffer) {
            $result = mysql_query($sql, $connection);
        } else {
            $result = mysql_unbuffered_query($sql, $connection);
            if (is_resource($result)) {
                $this->_lastResult = $result;
            }
        }

        if ($result === false) {
            echo '<pre>',
            htmlspecialchars($sql),
            htmlspecialchars(mysql_error($connection)),
            '</pre>';
            trigger_error(mysql_error($connection));
        }

        return $result;
    }

    /**
     * Send multiple SQL queries.
     *
     * @param string|array $sqlList An array of single query strings or a string of queries terminated with semi-colons.
     */
    public function queryN($sqlList) {
        if (!is_array($sqlList)) {
            $sqlList = explode(';', $sqlList);
        }

        foreach ($sqlList as $sql) {
            $sql = trim($sql);
            if ($sql) {
                $this->query($sql);
            }
        }
    }

    /**
     * Using RestrictedTables, determine if a table should be exported or not
     *
     * @param string $tableName Name of the table to check
     * @return bool True if table should be exported, false otherwise
     */
    public function shouldExport($tableName) {
        return empty($this->restrictedTables) || in_array(strtolower($tableName), $this->restrictedTables);
    }

    /**
     * Set database connection details.
     *
     * @param null $host
     * @param null $username
     * @param null $password
     * @param null $dbName
     */
    public function setConnection($host = null, $username = null, $password = null, $dbName = null) {
        $this->_host = $host;
        $this->_username = $username;
        $this->_password = $password;
        $this->_dbName = $dbName;
    }

    /**
     * Echo a status message to the console.
     *
     * @param $msg
     */
    public function status($msg) {
        if (defined('CONSOLE')) {
            echo $msg;
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
    public function structures($newStructures = false) {
        if (is_array($newStructures)) {
            $this->_structures = $newStructures;
        }

        return $this->_structures;
    }

    /**
     * Whether or not to use compression on the output file.
     *
     * @param bool $value The value to set or NULL to just return the value.
     * @return bool
     */
    public function useCompression($value = null) {
        if ($value !== null) {
            $this->_useCompression = $value;
        }

        return $this->_useCompression && $this->destination == 'file' && function_exists('gzopen');
    }

    /**
     * Returns the version of export file that will be created with this export.
     * The version is used when importing to determine the format of this file.
     *
     * @return string
     */
    public function version() {
        return APPLICATION_VERSION;
    }

    /**
     * Checks whether or not a table and columns exist in the database.
     *
     * @param string $table The name of the table to check.
     * @param array $columns An array of column names to check.
     * @return bool|array The method will return one of the following
     *  - true: If table and all of the columns exist.
     *  - false: If the table does not exist.
     *  - array: The names of the missing columns if one or more columns don't exist.
     */
    public function exists($table, $columns = array()) {
        static $_exists = array();

        if (!isset($_exists[$table])) {
            $result = $this->query("show table status like ':_$table'", true);
            if (!$result) {
                $_exists[$table] = false;
            } elseif (!mysql_fetch_assoc($result)) {
                $_exists[$table] = false;
            } else {
                mysql_free_result($result);
                $desc = $this->query('describe :_' . $table);
                if ($desc === false) {
                    $_exists[$table] = false;
                } else {
                    if (is_string($desc)) {
                        die($desc);
                    }

                    $cols = array();
                    while (($TD = mysql_fetch_assoc($desc)) !== false) {
                        $cols[$TD['Field']] = $TD;
                    }
                    mysql_free_result($desc);
                    $_exists[$table] = $cols;
                }
            }
        }

        if ($_exists[$table] == false) {
            return false;
        }

        $columns = (array)$columns;

        if (count($columns) == 0) {
            return true;
        }

        $missing = array();
        $cols = array_keys($_exists[$table]);
        foreach ($columns as $column) {
            if (!in_array($column, $cols)) {
                $missing[] = $column;
            }
        }

        return count($missing) == 0 ? true : $missing;
    }

    /**
     * Checks all required source tables are present.
     *
     * @param array $requiredTables
     * @return array|string
     */
    public function verifySource($requiredTables) {
        $missingTables = false;
        $countMissingTables = 0;
        $missingColumns = array();

        foreach ($requiredTables as $reqTable => $reqColumns) {
            $tableDescriptions = $this->query('describe :_' . $reqTable);
            //echo 'describe '.$prefix.$reqTable;
            if ($tableDescriptions === false) { // Table doesn't exist
                $countMissingTables++;
                if ($missingTables !== false) {
                    $missingTables .= ', ' . $reqTable;
                } else {
                    $missingTables = $reqTable;
                }
            } else {
                // Build array of columns in this table
                $presentColumns = array();
                while (($TD = mysql_fetch_assoc($tableDescriptions)) !== false) {
                    $presentColumns[] = $TD['Field'];
                }
                // Compare with required columns
                foreach ($reqColumns as $reqCol) {
                    if (!in_array($reqCol, $presentColumns)) {
                        $missingColumns[$reqTable][] = $reqCol;
                    }
                }

                mysql_free_result($tableDescriptions);
            }
        }

        // Return results
        if ($missingTables === false) {
            if (count($missingColumns) > 0) {
                $result = array();

                // Build a string of missing columns.
                foreach ($missingColumns as $table => $columns) {
                    $result[] = "The $table table is missing the following column(s): " . implode(', ', $columns);
                }

                return implode("<br />\n", $result);
            } else {
                return true;
            } // Nothing missing!
        } elseif ($countMissingTables == count($requiredTables)) {
            $result = 'The required tables are not present in the database. Make sure you entered the correct database name and prefix and try again.';

            // Guess the prefixes to notify the user.
            $prefixes = $this->getDatabasePrefixes();
            if (count($prefixes) == 1) {
                $result .= ' Based on the database you provided, your database prefix is probably ' . implode(', ',
                        $prefixes);
            } elseif (count($prefixes) > 0) {
                $result .= ' Based on the database you provided, your database prefix is probably one of the following: ' . implode(', ',
                        $prefixes);
            }

            return $result;
        } else {
            return 'Missing required database tables: ' . $missingTables;
        }
    }

    /**
     * Start table write to file.
     *
     * @param $fp
     * @param $tableName
     * @param $exportStructure
     */
    public function writeBeginTable($fp, $tableName, $exportStructure) {
        $tableHeader = '';

        foreach ($exportStructure as $key => $value) {
            if (is_numeric($key)) {
                $column = $value;
                $type = '';
            } else {
                $column = $key;
                $type = $value;
            }

            if (strlen($tableHeader) > 0) {
                $tableHeader .= self::DELIM;
            }

            if ($type) {
                $tableHeader .= $column . ':' . $type;
            } else {
                $tableHeader .= $column;
            }
        }

        fwrite($fp, 'Table: ' . $tableName . self::NEWLINE);
        fwrite($fp, $tableHeader . self::NEWLINE);
    }

    /**
     * End table write to file.
     *
     * @param $fp
     */
    public function writeEndTable($fp) {
        fwrite($fp, self::NEWLINE);
    }

    /**
     * Write a table's row to file.
     *
     * @param $fp
     * @param $row
     * @param $exportStructure
     * @param $revMappings
     */
    public function writeRow($fp, $row, $exportStructure, $revMappings) {
        $this->currentRow =& $row;

        // Loop through the columns in the export structure and grab their values from the row.
        $exRow = array();
        foreach ($exportStructure as $field => $type) {
            // Get the value of the export.
            $value = null;
            if (isset($revMappings[$field]) && isset($row[$revMappings[$field]['Column']])) {
                // The column is mapped.
                $value = $row[$revMappings[$field]['Column']];
            } elseif (array_key_exists($field, $row)) {
                // The column has an exact match in the export.
                $value = $row[$field];
            }

            // Check to see if there is a callback filter.
            $filtered = false;
            if (isset($revMappings[$field]['Filter'])) {
                $callback = $revMappings[$field]['Filter'];

                $row2 =& $row;
                $value = call_user_func($callback, $value, $field, $row2, $field);
                $row = $this->currentRow;
                $filtered = true;
            }

            // Format the value for writing.
            if (is_null($value)) {
                $value = self::NULL;
            } elseif (is_integer($value)) {
                // Do nothing, formats as is.
                // Only allow ints because PHP allows weird shit as numeric like "\n\n.1"
            } elseif (is_string($value) || is_numeric($value)) {
                // Check to see if there is a callback filter.
                if (!isset($revMappings[$field])) {
                    //$value = call_user_func($Filters[$field], $value, $field, $row);
                } else {
                    if (self::$mb && mb_detect_encoding($value) != 'UTF-8') {
                        $value = utf8_encode($value);
                    }
                }

                $value = str_replace(array("\r\n", "\r"), array(self::NEWLINE, self::NEWLINE), $value);
                $value = self::QUOTE
                    . str_replace(self::$escapeSearch, self::$escapeReplace, $value)
                    . self::QUOTE;
            } elseif (is_bool($value)) {
                $value = $value ? 1 : 0;
            } else {
                // Unknown format.
                $value = self::NULL;
            }

            $exRow[] = $value;
        }
        // Write the data.
        fwrite($fp, implode(self::DELIM, $exRow));
        // End the record.
        fwrite($fp, self::NEWLINE);
    }

    /**
     * SQL to get the file extension from a string.
     *
     * @param string $columnName
     * @return string SQL.
     */
    public static function fileExtension($columnName) {
        return "right($columnName, instr(reverse($columnName), '.') - 1)";
    }
}

?>
