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
class Migration
{
    /**
     * @var bool Whether to capture SQL without executing.
     */
    public bool $captureOnly = false;

    /**
     * @var array Any comments that have been written during the export.
     */
    public array $comments = [];

    /**
     * @var string DB prefix of source. Queries passed to export() will replace `:_` with this.
     * @see Migration::export()
     */
    protected string $srcPrefix = '';

    /**
     * @var array Table names to limit the export to. Full export is an empty array.
     */
    public array $limitedTables = [];

    /**
     * @var array Table structures that define the format of the intermediary export tables.
     */
    protected array $porterStructure = [];

    /**
     * @var DbFactory Instance DbFactory
     * @deprecated
     */
    protected DbFactory $database;

    /**
     * @var Storage Where the source data is from (read-only).
     */
    protected Storage $inputStorage;

    /**
     * @var Storage Where the mapping data is sent.
     */
    protected Storage $porterStorage;

    /**
     * @var Storage Where the target data is sent.
     */
    protected Storage $outputStorage;

    /** @var Storage Copy of where target data is sent for post-processing. */
    protected Storage $postscriptStorage;

    /**
     * Setup.
     *
     * @param DbFactory $inputDB Deprecated database connector used in Source packages.
     * @param Storage $inputStorage
     * @param Storage $porterStorage
     * @param Storage $outputStorage
     * @param Storage $postscriptStorage
     * @param array $porterStructure
     * @param string|null $limitTables
     * @param bool $captureOnly
     */
    public function __construct(
        DbFactory $inputDB, // @todo $inputStorage
        Storage $inputStorage,
        Storage $porterStorage,
        Storage $outputStorage,
        Storage $postscriptStorage,
        array $porterStructure,
        ?string $limitTables = '',
        bool $captureOnly = false
    ) {
        $this->database = $inputDB;
        $this->inputStorage = $inputStorage;
        $this->porterStorage = $porterStorage;
        $this->outputStorage = $outputStorage;
        $this->postscriptStorage = $postscriptStorage;
        $this->porterStructure = $porterStructure;
        $this->limitTables($limitTables);
        $this->captureOnly = $captureOnly;
    }

    /**
     * Provide the input database connection.
     *
     * @return Connection
     */
    public function dbInput(): Connection
    {
        return $this->inputStorage->getConnection();
    }

    /**
     * Provide the porter database connection.
     *
     * @return Connection
     */
    public function dbPorter(): Connection
    {
        return $this->porterStorage->getConnection();
    }

    /**
     * Provide the output database connection.
     *
     * @return Connection
     */
    public function dbOutput(): Connection
    {
        return $this->outputStorage->getConnection();
    }

    /**
     * Provide the postscript database connection.
     *
     * @return Connection
     */
    public function dbPostscript(): Connection
    {
        return $this->postscriptStorage->getConnection();
    }

    /**
     * Selective exports.
     *
     * 1. Get the comma-separated list of tables and turn it into an array
     * 2. Trim off the whitespace
     * 3. Normalize case to lower
     * 4. Save to the Migration instance
     *
     * @param ?string $tables
     */
    public function limitTables(?string $tables): void
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
    public function begin(): void
    {
        if ($this->captureOnly) {
            return;
        }
        $this->outputStorage->begin();
    }

    /**
     * Cleanup the target.
     */
    public function end(): void
    {
        if ($this->captureOnly) {
            return;
        }
        $this->outputStorage->end();
    }

    /**
     * Write a comment to the export file.
     *
     * @param string $message The message to write.
     * @param bool $echo Whether or not to echo the message in addition to writing it to the file.
     */
    public function comment($message, $echo = true): void
    {
        Log::comment($message);
        echo "\n" . $message;
    }

    /**
     * Export a collection of data, usually a table.
     *
     * @param string $tableName Name of table to export. This must correspond to one of the accepted map tables.
     * @param string|Builder $query The SQL query that will fetch the data for the export.
     * @param array $map Specifies mappings, if any, between source and export where keys represent source columns
     *   and values represent Vanilla columns.
     *   If you specify a Vanilla column then it must be in the export structure contained in this class.
     *   If you specify a MySQL type then the column will be added.
     *   If you specify an array you can have the following keys:
     *      Column (the new column name)
     *      Filter (the callable function name to process the data with)
     *      Type (the MySQL type)
     */
    public function export(string $tableName, string|Builder $query, array $map = [], array $filters = []): void
    {
        if (!empty($this->limitedTables) && !in_array(strtolower($tableName), $this->limitedTables)) {
            $this->comment("Skipping table: $tableName");
            return;
        }

        // Start timer.
        $start = microtime(true);

        // Validate table for export.
        if (!array_key_exists($tableName, $this->porterStructure)) {
            $this->comment("Error: $tableName is not a valid export.");
            return;
        }

        // Run the export query only if we got raw SQL from a legacy Source.
        if (is_string($query)) {
            $data = $this->query($query);
            if (empty($data)) {
                $this->comment("Error: No data found in $tableName.");
                return;
            }
        }

        $structure = $this->porterStructure[$tableName];

        // Reconcile data structure to be written to storage.
        list($map, $legacyFilter) = $this->normalizeDataMap($map); // @todo Update legacy filter usage and remove.
        $filters = array_merge($filters, $legacyFilter);

        // Prepare the storage medium for the incoming structure.
        $this->porterStorage->prepare($tableName, $structure);

        // Store the data.
        $info = $this->porterStorage->store($tableName, $map, $structure, $data ?? $query, $filters, $this);

        // Report.
        $this->reportStorage('export', $tableName, microtime(true) - $start, $info['rows'], $info['memory']);
    }

    /**
     * @param string $tableName
     * @param Builder $exp Connected to porterStorage.
     * @param array $struct
     * @param array $map
     * @param array $filters
     */
    public function import(string $tableName, Builder $exp, array $struct, array $map = [], array $filters = []): void
    {
        // Start timer.
        $start = microtime(true);

        // Prepare the storage medium for the incoming structure.
        $this->outputStorage->prepare($tableName, $struct);

        // Store the data.
        $info = $this->outputStorage->store($tableName, $map, $struct, $exp, $filters, $this);

        // Report.
        $this->reportStorage('import', $tableName, microtime(true) - $start, $info['rows'], $info['memory']);
    }

    /**
     * Create empty import tables.
     *
     * @param string $tableName
     * @param mixed[] $structure
     */
    public function importEmpty(string $tableName, array $structure): void
    {
        $this->outputStorage->prepare($tableName, $structure);
    }

    /**
     * Add log with results of a table storage action.
     *
     * @param string $action
     * @param string $table
     * @param float $timeElapsed
     * @param int $rowCount
     * @param int $memPeak
     */
    public function reportStorage(string $action, string $table, float $timeElapsed, int $rowCount, int $memPeak): void
    {
        // Format output.
        $report = sprintf(
            '%s: %s — %d rows, %s (%s)',
            $action,
            $table,
            $rowCount,
            formatElapsed($timeElapsed),
            formatBytes($memPeak)
        );
        $this->comment($report);
    }

    /**
     * Shim for storage method access.
     *
     * @param mixed[] $dataMap
     * @return mixed[]
     * @deprecated Awaits separating Source filters from $map
     */
    public function normalizeDataMap(array $dataMap): array
    {
        return $this->outputStorage->normalizeDataMap($dataMap);
    }

    /**
     * @return Storage
     */
    public function getOutputStorage(): Storage
    {
        return $this->outputStorage;
    }

    /**
     * @return Storage
     */
    public function getPostscriptStorage(): Storage
    {
        return $this->postscriptStorage;
    }

    /**
     * Ignore duplicates for a SQL storage target table. Adds prefix for you.
     *
     * @param string $tableName
     */
    public function ignoreOutputDuplicates(string $tableName): void
    {
        if (method_exists($this->outputStorage, 'ignoreTable')) {
            $this->outputStorage->ignoreTable($tableName);
        }
    }

    /**
     * Determine encoding of the input MySQL database & define a constant for filters.
     *
     * There's few quicker ways to corrupt your content than to pass your content through
     * PHP's HTML entity decoder (a common requirement for UGC) using the wrong encoding.
     *
     * Alt strategy: `SHOW CREATE TABLE $table;` then regex out: ` CHARSET={charset} `.
     * Or: Possibly just get collation + drop everything after first underscore.
     *
     * @param string $table Name of the database table to derive the encoding from.
     * @return string Encoding found.
     * @see HTMLDecoder()
     * @todo integration test
     */
    public function getInputEncoding(string $table): string
    {
        // Manually add table prefix.
        if (!$this->hasInputSchema($table)) {
            $this->comment('ERROR: Missing collation table ' . $this->dbInput()->getDatabaseName() .
                '.' . $this->dbInput()->getTablePrefix() . $table);
            return 'UTF-8';
        }
        $table = $this->dbInput()->getTablePrefix() . $table;

        // Derive the charset from the specified MySQL database table.
        $collation = $this->dbInput()
            ->select("show table status like '{$table}'")[0]->Collation;
        $charset = $this->dbInput()
            ->select("show collation like '{$collation}'")[0]->Charset ?? 'utf8mb4';
        if (\Porter\Config::getInstance()->debugEnabled()) {
            $this->comment('? Found charset: ' . $charset);
        }

        return match ($charset) {
            'latin1' => 'ISO-8859-1', // Western European
            'latin9' => 'ISO-8859-15', // Western European (adds Euro etc; not support by MySQL)
            'cp1250' => 'cp1250', // Windows, Western Europe
            default => 'UTF-8', // utf8mb4, utf8mb3, utf8
        };
    }

    /**
     * Execute query on inputDB() connection for backwards compatibility with Source packages.
     *
     * @param string $query The sql to execute.
     * @return ResultSet|false The query cursor.
     * @deprecated Need to remove ResultSet() from the Source packages.
     * @see self::dbInput()::unprepared()
     */
    public function query(string $query): ResultSet|false
    {
        $query = str_replace(':_', $this->dbInput()->getTablePrefix(), $query); // replace prefix.
        $query = rtrim($query, ';') . ';'; // guarantee semicolon.
        return $this->database->getInstance()->query($query);
    }

    /**
     * Check if the output storage schema exists.
     *
     * @param string $table
     * @param array $columns
     * @return bool
     */
    public function hasOutputSchema(string $table, array $columns = []): bool
    {
        return $this->outputStorage->exists($table, $columns);
    }

    /**
     * Check if the porter storage schema exists.
     *
     * @param string $table
     * @param array $columns
     * @return bool
     */
    public function hasPortSchema(string $table, array $columns = []): bool
    {
        return $this->porterStorage->exists($table, $columns);
    }

    /**
     * Check if the input storage schema exists.
     *
     * @param string $table The name of the table to check.
     * @param array|string $columns Column names to check.
     * @return bool Whether the table and all columns exist.
     */
    public function hasInputSchema(string $table, array|string $columns = []): bool
    {
        return $this->inputStorage->exists($table, $columns);
    }

    /**
     * Throws error if required source tables & columns are not present.
     *
     * @param array $requiredSchema Table => Columns
     * @deprecated
     * @see hasInputSchema() for a better way to detect this in Source packages.
     */
    public function verifySource(array $requiredSchema): void
    {
        $missingTables = [];
        $missingColumns = [];

        foreach ($requiredSchema as $table => $columns) {
            if (!$this->hasInputSchema($table)) { // Table is missing.
                $missingTables[] = $table;
            } else {
                foreach ($columns as $col) {
                    if (!$this->hasInputSchema($table, $col)) { // Column is missing.
                        $missingColumns[] = $table . '.' . $col;
                    }
                }
            }
        }
        if (!empty($missingTables)) {
            trigger_error('Missing required tables: ' . implode(', ', $missingTables));
        }
        if (!empty($missingColumns)) {
            trigger_error("Missing required columns: " . implode(', ', $missingColumns));
        }
    }

    /**
     * Determine if an index exists in a table
     *
     * @param string $indexName
     * @param string $table
     * @return bool
     */
    public function indexExists($indexName, $table): bool
    {
        $result = $this->query("show index from `$table` WHERE Key_name = '$indexName'");
        return $result->nextResultRow() !== false;
    }
}
