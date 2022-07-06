<?php

namespace Porter\Storage;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Porter\ConnectionManager;
use Porter\Database\ResultSet;
use Porter\ExportModel;
use Porter\Postscript;
use Porter\Storage;

class Database extends Storage
{
    public const INSERT_BATCH = 1000;

    /**
     * @var array Table structures that define the format of the intermediary export tables.
     */
    public array $mapStructure = []; // @todo

    /**
     * @var string Prefix for the storage database.
     */
    public string $prefix = '';

    /**
     * @var string Table name currently targeted by the batcher.
     */
    protected string $batchTable = '';

    /**
     * @var array List of tables that have already been reset to avoid dropping multipart import data.
     */
    public array $resetTables = [];

    /**
     * @var ConnectionManager
     */
    public ConnectionManager $connection;

    /**
     * @param ConnectionManager $c
     */
    public function __construct(ConnectionManager $c)
    {
        $this->connection = $c;
    }

    /**
     * Save the given records to the database. Use prefix.
     *
     * @param string $name
     * @param array $map
     * @param array $structure
     * @param ResultSet|Builder $data
     * @param array $filters
     * @param ExportModel $exportModel
     * @return array Information about the results.
     */
    public function store(
        string $name,
        array $map,
        array $structure,
        $data,
        array $filters,
        ExportModel $exportModel
    ): array {
        $info = [
            'rows' => 0,
            'memory' => 0,
        ];
        $this->setBatchTable($name);
        $this->connection->reset();

        if (is_a($data, '\Porter\Database\ResultSet')) {
            // Iterate on old ResultSet.
            while ($row = $data->nextResultRow()) {
                $info['rows']++;
                $row = $this->normalizeRow($map, $structure, $row, $filters);
                $bytes = $this->batchInsert($row);
                $info['memory'] = max($bytes, $info['memory']); // Highest memory usage.
            }
        } elseif (is_a($data, '\Illuminate\Database\Query\Builder')) {
            // Use the Builder to process results one at a time.
            foreach ($data->cursor() as $row) { // Using `chunk()` takes MUCH longer to process.
                $info['rows']++;
                $row = $this->normalizeRow($map, $structure, (array)$row, $filters);
                $bytes = $this->batchInsert($row);
                $info['memory'] = max($bytes, $info['memory']); // Highest memory usage.
            }
        }

        // Insert remaining records.
        $this->batchInsert([], true);

        return $info;
    }

    /**
     * Lower-level single record insert access.
     *
     * While `store()` takes a batch and processes it, this takes 1 row at a time.
     * Created for Postscripts to have finer control over record inserts.
     * @see Postscript — Where this method is most likely used (its children).
     * @see endStream — Must be called after using this method.
     *
     * @param array $row
     * @param array $structure
     * @return int
     */
    public function stream(array $row, array $structure): int
    {
        return $this->batchInsert($row);
    }

    /**
     * Send remaining batched records for insert.
     *
     * @return int
     */
    public function endStream(): int
    {
        return $this->batchInsert([], true);
    }

    /**
     * Accept rows one at a time and batch them together for more efficient inserts.
     *
     * @param array $row Row of data to insert.
     * @param bool $final Force an insert with existing batch.
     * @return int Bytes currently being used by the app.
     */
    private function batchInsert(array $row, bool $final = false): int
    {
        static $batch = [];
        if (!empty($row)) {
            $batch[] = $row;
        }
        $bytes = memory_get_usage(); // Measure before potential send.

        if (self::INSERT_BATCH === count($batch) || $final) {
            $this->sendBatch($batch);
            $batch = [];
        }

        return $bytes;
    }

    /**
     * Insert a batch of rows into the database.
     *
     * @param array $batch
     */
    private function sendBatch(array $batch)
    {
        $this->connection->newConnection()->table($this->getBatchTable())->insertOrIgnore($batch);
    }

    /**
     * Set table name that sendBatch() will target.
     *
     * @param string $tableName
     */
    private function setBatchTable(string $tableName)
    {
        $this->batchTable = $this->prefix . $tableName;
    }

    /**
     * Get table name that sendBatch() will target.
     *
     * @return string
     */
    private function getBatchTable(): string
    {
        return $this->batchTable;
    }

    /**
     * Create fresh table for storage. Use prefix.
     *
     * @param string $name
     * @param array $structure
     */
    public function prepare(string $name, array $structure): void
    {
        if (!in_array($name, $this->resetTables)) {
            // Avoid double-dropping a table during an import because we probably already put data in it.
            $this->createTable($this->prefix . $name, $this->getTableStructureClosure($structure));
        }
        $this->resetTables[] = $name;
        $this->setBatchTable($name);
    }

    /**
     * Create a new table if it doesn't already exist.
     *
     * @param string $name
     * @param callable $closure
     */
    public function createTable(string $name, callable $closure)
    {
        $dbm = $this->connection->dbm->getConnection($this->connection->getAlias());
        $schema = $dbm->getSchemaBuilder();
        if ($this->exists($name)) {
            // Empty the table if it already exists.
            // Foreign key check must be disabled or MySQL throws error.
            $dbm->unprepared("SET foreign_key_checks = 0");
            $dbm->query()->from($name)->truncate();
            // @todo Check column integrity too.
        } else {
            // Create table if it does not.
            $schema->create($name, $closure);
        }
    }

    /**
     * Whether the requested table & columns exist.
     *
     * @see ExportModel::exists()
     * @param string $tableName
     * @param array $columns
     * @return bool
     */
    public function exists(string $tableName, array $columns = []): bool
    {
        $schema = $this->connection->dbm->getConnection($this->connection->getAlias())->getSchemaBuilder();
        if (empty($columns)) {
            // No columns requested.
            return $schema->hasTable($tableName);
        }
        // Table must exist and columns were requested.
        return $schema->hasTable($tableName) && $schema->hasColumns($tableName, $columns);
    }

    /**
     * Converts a simple array of Column => Type into a callable table structure.
     *
     * Ideally, we'd just pass structures in the correct format to start with.
     * Unfortunately, this isn't greenfield software, and today it's less-bad
     * to write this method than to try to convert thousands of these manually.
     *
     * @see https://laravel.com/docs/9.x/migrations#creating-columns
     *
     * @param array $tableInfo Keys are column names, values are MySQL data types.
     * @return callable Closure defining a single Illuminate Database table.
     */
    public function getTableStructureClosure(array $tableInfo): callable
    {
        // Build the closure using given structure.
        return function (Blueprint $table) use ($tableInfo) {
            // One statement per column to be created.
            foreach ($tableInfo as $columnName => $type) {
                if (is_array($type)) {
                    // Handle enums first (blocking potential `strpos()` on an array).
                    $table->enum($columnName, $type)->nullable(); // $type == $options.
                } elseif (strpos($type, 'varchar') === 0) {
                    // Handle varchars.
                    $length = $this->getVarcharLength($type);
                    $table->string($columnName, $length)->nullable();
                } elseif (strpos($type, 'varbinary') === 0) {
                    // Handle varbinary as blobs.
                    $table->binary($columnName)->nullable();
                } else {
                    // Handle everything else.
                    // But first, un-abbreviate 'int' (e.g. `bigint`, `tinyint(1)`).
                    $type = preg_replace('/int($|\()/', 'integer', $type);
                    $table->$type($columnName)->nullable();
                }
            }
        };
    }

    /**
     * @param string $prefix Database table prefix.
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * Disable foreign key & secondary unique checking temporarily for import.
     *
     * Does not disable primary unique key enforcement (which is not possible).
     * Required by interface.
     */
    public function begin()
    {
        $dbm = $this->connection->dbm->getConnection($this->connection->getAlias());
        $dbm->unprepared("SET foreign_key_checks = 0");
        $dbm->unprepared("SET unique_checks = 0");
    }

    /**
     * Re-enable foreign key & secondary unique checking.
     *
     * Does not enforce constraints on existing data.
     * Required by interface.
     */
    public function end()
    {
        $dbm = $this->connection->dbm->getConnection($this->connection->getAlias());
        $dbm->unprepared("SET foreign_key_checks = 1");
        $dbm->unprepared("SET unique_checks = 1");
    }

    /**
     * @param string $type
     * @return int
     */
    public function getVarcharLength($type): int
    {
        $matches = [];
        preg_match('/varchar\(([0-9]{1,3})\)/', $type, $matches);
        return (int)$matches[1] ?: 100;
    }
}
