<?php

/**
 *
 */

namespace Porter;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Porter\Database\DbFactory;
use Porter\Database\ResultSet;

/**
 * Object for exporting other database structures into a format that can be imported.
 */
class ExportModel
{
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
    public string $characterSet = 'utf8mb4';

    /**
     * @var string DB prefix of source. Queries passed to export() will replace `:_` with this.
     * @see ExportModel::export()
     */
    public string $srcPrefix = '';

    /**
     * @var string DB prefix of the intermediate storage. @todo Should be a constant; don't alter.
     */
    public string $intPrefix = 'PORT_';

    /**
     * @var string DB prefix of the final target. Blindly prepended to give table name.
     */
    public string $tarPrefix = '';

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
    public array $mapStructure = [];

    /**
     * @var DbFactory Instance DbFactory
     * @deprecated
     */
    protected DbFactory $database;

    /**
     * Source connection for the import step.
     *
     * @var ConnectionManager
     */
    protected ConnectionManager $importSourceCM;

    /**
     * @var Storage Where the data is being sent.
     */
    protected Storage $storage;

    /**
     * Setup.
     *
     * @param $db
     * @param $map
     * @param $storage
     * @param ConnectionManager $importSourceCM
     */
    public function __construct($db, $map, $storage, ConnectionManager $importSourceCM)
    {
        $this->database = $db;
        $this->mapStructure = $map;
        $this->storage = $storage;
        $importSourceCM->newConnection();
        $this->importSourceCM = $importSourceCM;
    }

    /**
     * Provide the import database.
     *
     * @return Connection
     */
    public function dbImport(): Connection
    {
        return $this->importSourceCM->connection();
    }

    /**
     * Selective exports.
     *
     * 1. Get the comma-separated list of tables and turn it into an array
     * 2. Trim off the whitespace
     * 3. Normalize case to lower
     * 4. Save to the ExportModel instance
     *
     * @param string $tables
     */
    public function limitTables(string $tables)
    {
        if (!empty($tables)) {
            $tables = explode(',', $tables);
            $tables = array_map('trim', $tables);
            $tables = array_map('strtolower', $tables);
            $this->limitedTables = $tables;
        }
    }

    /**
     * Prepare the target.
     */
    public function begin()
    {
        if ($this->captureOnly) {
            return;
        }
        $this->storage->begin();
    }

    /**
     * Cleanup the target.
     */
    public function end()
    {
        if ($this->captureOnly) {
            return;
        }
        $this->storage->end();
    }

    /**
     * Write a comment to the export file.
     *
     * @param string $message The message to write.
     * @param bool $echo Whether or not to echo the message in addition to writing it to the file.
     */
    public function comment($message, $echo = true)
    {
        Log::comment($message);
        echo "\n" . $message;
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
    public function export(string $tableName, string $query, array $map = [], array $filters = [])
    {
        if (!empty($this->limitedTables) && !in_array(strtolower($tableName), $this->limitedTables)) {
            $this->comment("Skipping table: $tableName");
            return;
        }

        // Start timer.
        $start = microtime(true);

        // Validate table for export.
        if (!array_key_exists($tableName, $this->mapStructure)) {
            $this->comment("Error: $tableName is not a valid export.");
            return;
        }

        // Do the export.
        $query = $this->processQuery($query);
        $data = $this->executeQuery($query); // @todo Use new db layer.
        if (empty($data)) {
            $this->comment("Error: No data found in $tableName.");
            return;
        }

        $structure = $this->mapStructure[$tableName];

        // Reconcile data structure to be written to storage.
        list($map, $legacyFilter) = $this->normalizeDataMap($map); // @todo Update legacy filter usage and remove.
        $filters = array_merge($filters, $legacyFilter);

        // Set storage prefix.
        $this->storage->setPrefix($this->intPrefix);

        // Prepare the storage medium for the incoming structure.
        $this->storage->prepare($tableName, $structure);

        // Store the data.
        $info = $this->storage->store($tableName, $map, $structure, $data, $filters, $this);

        // Report.
        $this->reportStorage('export', $tableName, microtime(true) - $start, $info['rows'], $info['memory']);
    }

    /**
     * @param string $tableName
     * @param Builder $exp
     * @param array $structure
     * @param array $map
     * @param array $filters
     */
    public function import(string $tableName, Builder $exp, array $structure, array $map = [], array $filters = [])
    {
        // Start timer.
        $start = microtime(true);

        // Set storage prefix.
        $this->storage->setPrefix($this->tarPrefix);

        // Prepare the storage medium for the incoming structure.
        $this->storage->prepare($tableName, $structure);

        // Store the data.
        $info = $this->storage->store($tableName, $map, $structure, $exp, $filters, $this);

        // Report.
        $this->reportStorage('import', $tableName, microtime(true) - $start, $info['rows'], $info['memory']);
    }

    /**
     * Create empty import tables.
     *
     * @param string $tableName
     * @param $structure
     */
    public function importEmpty(string $tableName, $structure): void
    {
        $this->storage->prepare($tableName, $structure);
    }

    /**
     * Add log with results of a table storage action.
     *
     * @param string $action
     * @param string $table
     * @param float $timeElapsed
     * @param int $rowCount
     * @param int $memoryPeak
     */
    public function reportStorage(string $action, string $table, float $timeElapsed, int $rowCount, int $memoryPeak)
    {
        // Format output.
        $report = sprintf(
            '%s: %s — %d rows, %s (%s)',
            $action,
            $table,
            $rowCount,
            formatElapsed($timeElapsed),
            formatBytes($memoryPeak)
        );
        $this->comment($report);
    }

    /**
     * Shim for storage method access.
     *
     * @deprecated
     * @param array $dataMap
     * @return array
     */
    public function normalizeDataMap(array $dataMap): array
    {
        return $this->storage->normalizeDataMap($dataMap);
    }

    /**
     * Create an array of `strtolower(name)` => ID for doing lookups later.
     *
     * @todo This strategy likely won't scale past 100K users. 18K users @ +8mb memory use.
     *
     * @return array
     */
    public function buildUserMap(): array
    {
        $userMap = $this->dbImport()
            ->table($this->tarPrefix . 'users')
            ->get(['id', 'username']);

        $users = [];
        foreach ($userMap as $user) {
            // Use the first found ID for each name in case of duplicates.
            if (!isset($users[strtolower($user->username)])) {
                $users[strtolower($user->username)] = $user->id;
            }
        }

        // Record memory usage from user map.
        $this->comment('Mentions map memory usage at ' . formatBytes(memory_get_usage()));

        return $users;
    }

    /**
     * Find duplicate records on the given table + column.
     *
     * @param string $table
     * @param string $column
     * @return array
     */
    public function findDuplicates(string $table, string $column): array
    {
        $results = [];
        $db = $this->dbImport();
        $duplicates = $db->table($table)
            ->select($column, $db->raw('count(' . $column . ') as found_count'))
            ->groupBy($column)
            ->having('found_count', '>', '1')
            ->get();
        foreach ($duplicates as $dupe) {
            $results[] = $dupe->$column;
        }
        return $results;
    }

    /**
     * Prune records where a foreign key doesn't exist for them.
     *
     * This happens in the Porter format / intermediary step.
     * It must be complete BEFORE records are inserted into the Target due to FK constraints.
     *
     * @param string $table Table to prune.
     * @param string $column Column (likely a key) to be compared to the foreign key for its existence.
     * @param string $fnTable Foreign table to check for corresponding key.
     * @param string $fnColumn Foreign key to select.
     */
    public function pruneOrphanedRecords(string $table, string $column, string $fnTable, string $fnColumn)
    {
        // `DELETE FROM $table WHERE $column NOT IN (SELECT $fnColumn FROM $fnTable)`
        $db = $this->dbImport();
        $duplicates = $db->table($table)
            ->whereNotIn($column, $db->table($fnTable)->pluck($fnColumn))
            ->delete();
    }

    /**
     * Do preprocessing on the database query.
     *
     * @param string $query
     * @return string
     */
    protected function processQuery(string $query): string
    {
        // Check for a chunked query.
        $query = str_replace('{from}', '-2000000000', $query);
        $query = str_replace('{to}', '2000000000', $query);

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
     * Applying filter to permission column.
     *
     * @param array $columns
     * @return array
     * @deprecated
     */
    public function fixPermissionColumns(array $columns)
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
     * Ignore duplicates for a SQL storage target table. Adds prefix for you.
     *
     * @param string $tableName
     */
    public function ignoreDuplicates(string $tableName)
    {
        if (method_exists($this->storage, 'ignoreTable')) {
            $this->storage->ignoreTable($this->tarPrefix . $tableName);
        }
    }

    /**
     * Execute an sql statement and return the entire result as an associative array.
     *
     * @param string $sql
     * @param bool|string $indexColumn
     * @return array
     * @deprecated
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
        $characterSet = 'utf8mb4'; // Default.

        // First get the collation for the database.
        $data = $this->query("show table status like ':_{$table}';");
        if (!$data) {
            return;
        }
        if ($statusRow = $data->nextResultRow()) {
            $collation = $statusRow['Collation'];
        } else {
            return;
        }
        unset($data);

        // Grab the character set from the database.
        $data = $this->query("show collation like '$collation'");
        if (!$data) {
            return;
        }
        if ($collationRow = $data->nextResultRow()) {
            $characterSet = $collationRow['Charset'];
            if (!defined('PORTER_CHARACTER_SET')) {
                define('PORTER_CHARACTER_SET', $characterSet);
            }
            return;
        }

        $this->characterSet = $characterSet;
    }

    /**
     * Execute a SQL query on the current connection.
     *
     * Wrapper for _Query().
     *
     * @param string $query The sql to execute.
     * @return ResultSet|false The query cursor.
     */
    public function query(string $query)
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
     * @deprecated
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
     * Check if the storage container exists for this data.
     *
     * @see ExportModel::exists() — Equivalent for source.
     * @param string $table
     * @param array $columns
     * @return bool
     */
    public function targetExists(string $table, array $columns = []): bool
    {
        return $this->storage->exists($table, $columns);
    }

    /**
     * Checks whether or not a table and columns exist in the database.
     *
     * @param string $table The name of the table to check.
     * @param array|string $columns An array of column names to check.
     * @return bool Whether the table and all columns exist.
     */
    public function exists(string $table, $columns = [])
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

        return count($missing) == 0;
    }

    /**
     * Checks all required source tables are present.
     *
     * @param array $requiredTables
     */
    public function verifySource(array $requiredTables)
    {
        $missingTables = false;
        $countMissingTables = 0;
        $missingColumns = array();

        foreach ($requiredTables as $reqTable => $reqColumns) {
            $tableDescriptions = $this->executeQuery('describe `:_' . $reqTable . '`');
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
     * Execute a SQL query on the current connection.
     *
     * @param string $sql
     * @return ResultSet|false instance of ResultSet of success false on failure
     */
    private function executeQuery(string $sql)
    {
        $sql = str_replace(':_', $this->srcPrefix, $sql); // replace prefix.
        $sql = rtrim($sql, ';') . ';'; // guarantee semicolon.

        $dbResource = $this->database->getInstance();
        return $dbResource->query($sql);
    }

    /**
     * Escaping string using the db resource
     *
     * @param string $string
     * @return string escaped string
     * @deprecated
     */
    public function escape(string $string): string
    {
        $dbResource = $this->database->getInstance();
        return $dbResource->escape($string);
    }

    /**
     * Determine if an index exists in a table
     *
     * @param string $indexName
     * @param string $table
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
    public function tableExists(string $tableName): bool
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
    public function columnExists(string $tableName, string $columnName): bool
    {
        $result = $this->query(
            " select column_name
                from information_schema.columns
                where table_schema = database()
                    and table_name = '$tableName'
                    and column_name = '$columnName'"
        );
        return $result->nextResultRow() !== false;
    }
}
