<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace Porter;

use Porter\Database\DbResource;
use Porter\Database\ResultSet;
use Porter\Log;

/**
 * Object for exporting other database structures into a format that can be imported.
 */
class ExportModel
{
    /**
     * Character constants.
     */
    public const COMMENT = '//';
    public const DELIM = ',';
    public const ESCAPE = '\\';
    public const NEWLINE = "\n";
    public const NULL = '\N';
    public const QUOTE = '"';

    /**
     * @var bool Whether to capture SQL without executing.
     */
    public bool $captureOnly = false;

    /**
     * @var bool Whether to limit results to the $testLimit.
     */
    public bool $testMode = false;

    /**
     * @var int How many records to limit when $testMode is enabled.
     */
    public int $testLimit = 10;

    /**
     * @var array Any comments that have been written during the export.
     */
    public array $comments = [];

    /**
     * @var string The charcter set to set as the connection anytime the database connects.
     */
    public string $characterSet = 'utf8';

    /**
     * @var array Storage for sloppy data passing.
     * @deprecated
     */
    public array $currentRow = [];

    /**
     * @var string Prefix for the target database.
     */
    public string $destPrefix = 'PORT_';

    /**
     * @var string Name of target database.
     */
    public string $destDb = '';

    /**
     * @var resource File pointer
     */
    public $file = null;

    /**
     * @var string The path to the export file.
     * @deprecated
     */
    public string $path = '';

    /**
     * @var string DB prefix. SQL strings passed to ExportTable() will replace occurances of :_ with this.
     * @see ExportModel::export()
     * @deprecated
     */
    public string $prefix = '';

    /**
     * @var array Log of all queries done in this run for debugging / test mode.
     */
    public array $queryRecord = [];

    /**
     * @var array Table names to limit the export to. Full export is an empty array.
     */
    public array $limitedTables = [];

    /**
     * @var array Table structures that define the format of the intermediary export tables.
     */
    protected array $mapStructure = [];

    /**
     * @var bool Whether or not to use compression when creating the file.
     */
    protected bool $useCompression = true;

    /**
     * @var DbResource Instance DbFactory
     * @deprecated
     */
    protected DbResource $database;

    /**
     * Setup.
     */
    public function __construct($db, $map)
    {
        $this->database = $db;
        $this->mapStructure = $map;
    }

    /**
     * Selective exports.
     *
     * 1. Get the comma-separated list of tables and turn it into an array
     * 2. Trim off the whitespace
     * 3. Normalize case to lower
     * 4. Save to the ExportModel instance
     *
     * @param string $restrictedTables
     */
    public function loadTables(string $restrictedTables)
    {
        if (!empty($restrictedTables)) {
            $restrictedTables = explode(',', $restrictedTables);

            if (is_array($restrictedTables) && !empty($restrictedTables)) {
                $restrictedTables = array_map('trim', $restrictedTables);
                $restrictedTables = array_map('strtolower', $restrictedTables);

                $this->limitedTables = $restrictedTables;
            }
        }
    }

    /**
     * Create the export file and begin the export.
     */
    public function beginExport()
    {
        $this->comments = array();

        // Allow us to define where the output file goes.
        if (Request::instance()->get('destpath')) {
            $this->path = Request::instance()->get('destpath');
            if (strstr($this->path, '/') !== false && substr($this->path, 1, -1) != '/') {
                // We're using slash paths but didn't include a final slash.
                $this->path .= '/';
            }
        }

        // Allow the $path parameter to override this default naming.
        $this->path .= 'export_' . date('Y-m-d_His') . '.txt' . ($this->useCompression() ? '.gz' : '');

        // Start the file pointer.
        $fp = $this->openFile();

        // Build meta info about where this file came from.
        $comment = 'Nitro Porter Export';

        // Add meta info to the output.
        if ($this->captureOnly) {
            $this->comment($comment);
        } else {
            fwrite($fp, $comment . self::NEWLINE . self::NEWLINE);
        }

        $this->comment('Export Started: ' . date('Y-m-d H:i:s'));
    }

    /**
     * Write a comment to the export file.
     *
     * @param string $message The message to write.
     * @param bool   $echo    Whether or not to echo the message in addition to writing it to the file.
     */
    public function comment($message, $echo = true)
    {
        $comment = self::COMMENT . ' ' . str_replace(
            self::NEWLINE,
            self::NEWLINE . self::COMMENT . ' ',
            $message
        ) . self::NEWLINE;

        Log::comment($comment);
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
    public function endExport($time)
    {
        $this->comment($this->path);
        $this->comment('Export Completed: ' . date('Y-m-d H:i:s'));
        $this->comment(sprintf('Elapsed Time: %s', formatElapsed($time)));

        if ($this->testMode || Request::instance()->get('dumpsql') || $this->captureOnly) {
            $queries = implode("\n\n", $this->queryRecord);
            $this->comment($queries, true);
        }

        if ($this->useCompression()) {
            gzclose($this->file);
        } else {
            fclose($this->file);
        }
    }

    /**
     * Export a collection of data, usually a table.
     *
     * @param string $tableName Name of table to export. This must correspond to one of the accepted map tables.
     * @param string $query The SQL query that will fetch the data for the export.
     * @param array $map Specifies mappings, if any, between source and export where keys represent source columns
     *   and values represent Vanilla columns.
     *   If you specify a Vanilla column then it must be in the export structure contained in this class.
     *   If you specify a MySQL type then the column will be added.
     *   If you specify an array you can have the following keys:
     *      Column (the new column name)
     *      Filter (the callable function name to process the data with)
     *      Type (the MySQL type)
     */
    public function export(string $tableName, string $query, array $map = [])
    {
        if (!empty($this->limitedTables) && !in_array(strtolower($tableName), $this->limitedTables)) {
            $this->comment("Skipping table: $tableName");
            return;
        }

        $start = microtime(true);
        $rowCount = $this->exportTableWrite($tableName, $query, $map);
        $elapsed = formatElapsed($start - microtime(true));
        $this->comment("Exported Table: $tableName ($rowCount rows, $elapsed)");
    }

    /**
     * Process for writing an entire single table to file.
     *
     * @param  string $tableName
     * @param  string $query
     * @param  array $map
     * @return int
     */
    protected function exportTableWrite(string $tableName, string $query, array $map = []): int
    {
        // Make sure the table is valid for export.
        if (!array_key_exists($tableName, $this->mapStructure)) {
            $this->comment("Error: $tableName is not a valid export.");
            return 0;
        }

        $query = $this->processQuery($query);
        $data = $this->executeQuery($query);

        $structure = $this->mapStructure[$tableName];
        $firstQuery = true;
        $fp = $this->file;

        // Loop through the data and write it to the file.
        $rowCount = 0;
        if ($data !== false) {
            while ($row = $data->nextResultRow()) {
                $row = (array)$row; // export%202010-05-06%20210937.txt
                $this->currentRow =& $row;
                $rowCount++;

                if ($firstQuery) {
                    // Get the export structure.
                    $exportStructure = $this->getExportStructure($row, $structure, $map, $tableName);
                    $revMappings = $this->flipMappings($map);
                    $this->writeBeginTable($fp, $tableName, $exportStructure);

                    $firstQuery = false;
                }
                $this->writeRow($fp, $row, $exportStructure, $revMappings);
            }
        }
        unset($data);
        $this->writeEndTable($fp);

        return $rowCount;
    }


    /**
     * @param string $query
     * @return string
     */
    protected function processQuery(string $query): string
    {
        // Check for a chunked query.
        $query = str_replace('{from}', -2000000000, $query);
        $query = str_replace('{to}', 2000000000, $query);

        // If we are in test mode then limit the query.
        if ($this->testMode && $this->testLimit) {
            $query = rtrim($query, ';');
            if (stripos($query, 'select') !== false && stripos($query, 'limit') === false) {
                $query .= " limit {$this->testLimit}";
            }
        }
        return $query;
    }

    /**
     * @param $value
     * @return string
     */
    public function escapedValue($value): string
    {
        // Set the search and replace to escape strings.
        $escapeSearch = [
            self::ESCAPE, // escape must go first
            self::DELIM,
            self::NEWLINE,
            self::QUOTE
        ];
        $escapeReplace = [
            self::ESCAPE . self::ESCAPE,
            self::ESCAPE . self::DELIM,
            self::ESCAPE . self::NEWLINE,
            self::ESCAPE . self::QUOTE
        ];

        return self::QUOTE
            . str_replace($escapeSearch, $escapeReplace, $value)
            . self::QUOTE;
    }

    /**
     *
     *
     * @param $tableName
     * @param $query
     * @param array $mappings
     */
    protected function createExportTable($tableName, $query, $mappings = [])
    {
        // Limit the query to grab any additional columns.
        $queryStruct = rtrim($query, ';') . ' limit 1';
        $structure = $this->mapStructure[$tableName];

        $data = $this->query($queryStruct, true);
        //      $mb = function_exists('mb_detect_encoding');

        // Loop through the data and write it to the file.
        if ($data === false) {
            return;
        }

        // Get the export structure.
        while (($row = $data->nextResultRow()) !== false) {
            $row = (array)$row;

            // Get the export structure.
            $exportStructure = $this->getExportStructure($row, $structure, $mappings, $tableName);

            break;
        }

        // Build the create table statement.
        $columnDefs = array();
        foreach ($exportStructure as $columnName => $type) {
            $columnDefs[] = "`$columnName` $type";
        }
        $destDb = '';
        if (!empty($this->destDb)) {
            $destDb = $this->destDb . '.';
        }

        $this->query("drop table if exists {$destDb}{$this->destPrefix}$tableName");
        $createSql = "create table {$destDb}{$this->destPrefix}$tableName (\n  " . implode(
            ",\n  ",
            $columnDefs
        ) . "\n) engine=innodb";

        $this->query($createSql);
    }

    /**
     * Applying filter to permission column.
     *
     * @param  $columns
     * @return array
     */
    public function fixPermissionColumns($columns)
    {
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
     * Flip keys and values of associative array.
     *
     * @param  $mappings
     * @return array
     */
    public function flipMappings($mappings)
    {
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
     * Execute an sql statement and return the entire result as an associative array.
     *
     * @param  string $sql
     * @param  bool   $indexColumn
     * @return array
     */
    public function get($sql, $indexColumn = false)
    {
        $r = $this->executeQuery($sql);
        $result = [];

        while ($row = ($r->nextResultRow())) {
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
     * @param string $table Table to derive charset from.
     */
    public function setCharacterSet(string $table)
    {
        $characterSet = 'utf8'; // Default.
        $update = true;

        // First get the collation for the database.
        $data = $this->query("show table status like ':_{$table}';");
        if (!$data) {
            $update = false;
        }
        if ($statusRow = $data->nextResultRow()) {
            $collation = $statusRow['Collation'];
        } else {
            $update = false;
        }
        unset($data);

        // Grab the character set from the database.
        $data = $this->query("show collation like '$collation'");
        if (!$data) {
            $update = false;
        }
        if ($collationRow = $data->nextResultRow()) {
            $characterSet = $collationRow['Charset'];
            if (!defined('PORTER_CHARACTER_SET')) {
                define('PORTER_CHARACTER_SET', $characterSet);
            }
            $update = false;
        }

        if ($update) {
            $this->characterSet = $characterSet;
        }
    }

    /**
     *
     *
     * @param  $row
     * @param  $tableOrStructure
     * @param  $mappings
     * @param  string $tableName
     * @return array
     */
    public function getExportStructure($row, $tableOrStructure, &$mappings, $tableName = '_')
    {
        $exportStructure = array();

        if (is_string($tableOrStructure)) {
            $structure = $this->mapStructure[$tableOrStructure];
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
                    } elseif (
                        is_array($testMapping)
                        && array_key_exists('Column', $testMapping)
                        && ($testMapping['Column'] == $column)
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
     * @return resource
     */
    protected function openFile()
    {
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
     * @param  string $query The sql to execute.
     * @return ResultSet|string|false The query cursor.
     */
    public function query($query)
    {
        if (!preg_match('`limit 1;$`', $query)) {
            $this->queryRecord[] = $query;
        }
        return $this->executeQuery($query);
    }

    /**
     * Send multiple SQL queries.
     *
     * @param string|array $sqlList An array of single query strings or a string of queries terminated with semi-colons.
     */
    public function queryN($sqlList)
    {
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
     * Whether or not to use compression on the output file.
     *
     * @param  bool $value The value to set or NULL to just return the value.
     * @return bool
     */
    public function useCompression($value = null)
    {
        if ($value !== null) {
            $this->useCompression = $value;
        }

        return $this->useCompression && function_exists('gzopen');
    }

    /**
     * Checks whether or not a table and columns exist in the database.
     *
     * @param  string $table   The name of the table to check.
     * @param  array  $columns An array of column names to check.
     * @return bool|array The method will return one of the following
     *  - true: If table and all of the columns exist.
     *  - false: If the table does not exist.
     *  - array: The names of the missing columns if one or more columns don't exist.
     */
    public function exists($table, $columns = [])
    {
        static $_exists = array();

        if (!isset($_exists[$table])) {
            $result = $this->query("show table status like ':_$table'");
            if (!$result) {
                $_exists[$table] = false;
            } elseif (!$result->nextResultRow()) {
                $_exists[$table] = false;
            } else {
                $desc = $this->query('describe :_' . $table);
                if ($desc === false) {
                    $_exists[$table] = false;
                } else {
                    if (is_string($desc)) {
                        die($desc);
                    }

                    $cols = array();
                    while (($TD = $desc->nextResultRow()) !== false) {
                        $cols[$TD['Field']] = $TD;
                    }
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
     * @param  array $requiredTables
     */
    public function verifySource(array $requiredTables)
    {
        $missingTables = false;
        $countMissingTables = 0;
        $missingColumns = array();

        foreach ($requiredTables as $reqTable => $reqColumns) {
            $tableDescriptions = $this->executeQuery('describe :_' . $reqTable);

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
                while (($TD = $tableDescriptions->nextResultRow()) !== false) {
                    $presentColumns[] = $TD['Field'];
                }
                // Compare with required columns
                foreach ($reqColumns as $reqCol) {
                    if (!in_array($reqCol, $presentColumns)) {
                        $missingColumns[$reqTable][] = $reqCol;
                    }
                }
            }
        }
        // Return results
        if ($missingTables === false) {
            if (count($missingColumns) > 0) {
                $error = [];
                // Build a string of missing columns.
                foreach ($missingColumns as $table => $columns) {
                    $error[] = "The $table table is missing the following column(s): " . implode(', ', $columns);
                }
                trigger_error(implode("<br />\n", $error));
            }
        } elseif ($countMissingTables == count($requiredTables)) {
            $error = 'The required tables are not present in the database.
                Make sure you entered the correct database name and prefix and try again.';

            trigger_error($error);
        } else {
            trigger_error('Missing required database tables: ' . $missingTables);
        }
    }

    /**
     * Start table write to file.
     *
     * @param resource $fp
     * @param string $tableName
     * @param array $exportStructure
     */
    public function writeBeginTable($fp, $tableName, $exportStructure)
    {
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
     * @param resource $fp
     */
    public function writeEndTable($fp)
    {
        fwrite($fp, self::NEWLINE);
        fwrite($fp, self::NEWLINE);
    }

    /**
     * Write a table's row to file.
     *
     * @param resource $fp
     * @param array $row
     * @param array $exportStructure
     * @param array $revMappings
     */
    public function writeRow($fp, $row, $exportStructure, $revMappings)
    {
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
            if (isset($revMappings[$field]['Filter'])) {
                $callback = $revMappings[$field]['Filter'];

                $row2 =& $row;
                $value = call_user_func($callback, $value, $field, $row2, $field);
                $row = $this->currentRow;
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
                    if (function_exists('mb_detect_encoding') && mb_detect_encoding($value) != 'UTF-8') {
                        $value = utf8_encode($value);
                    }
                }

                $value = str_replace(array("\r\n", "\r"), array(self::NEWLINE, self::NEWLINE), $value);
                $value = $this->escapedValue($value);
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
     * Execute a SQL query on the current connection.
     *
     * @param string $sql
     * @return ResultSet|false instance of ResultSet of success false on failure
     */
    private function executeQuery($sql)
    {
        $sql = str_replace(':_', $this->prefix, $sql); // replace prefix.

        $sql = rtrim($sql, ';') . ';';

        $dbResource = $this->database->getInstance();
        return $dbResource->query($sql);
    }

    /**
     * Escaping string using the db resource
     *
     * @param string $string
     * @return string escaped string
     */
    public function escape($string)
    {
        $dbResource = $this->database->getInstance();
        return $dbResource->escape($string);
    }

    /**
     * Determine if an index exists in a table
     *
     * @param  string $indexName
     * @param  string $table
     * @return bool
     */
    public function indexExists($indexName, $table)
    {
        $result = $this->query("show index from `$table` WHERE Key_name = '$indexName'");

        return $result->nextResultRow() !== false;
    }

    /**
     * Determine if a table exists
     *
     * @param string $tableName
     * @return bool
     */
    public function tableExists($tableName)
    {
        $result = $this->query("show tables like '$tableName'");

        return !empty($result->nextResultRow());
    }

    /**
     * Determine if a column exists in a table
     *
     * @param string $tableName
     * @param string $columnName
     * @return bool
     */
    public function columnExists($tableName, $columnName)
    {
        $result = $this->query(
            "
            select column_name
            from information_schema.columns
            where table_schema = database()
                and table_name = '$tableName'
                and column_name = '$columnName'
        "
        );
        return $result->nextResultRow() !== false;
    }
}
