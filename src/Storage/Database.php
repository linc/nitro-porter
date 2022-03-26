<?php

namespace Porter\Storage;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Porter\Connection;
use Porter\Database\ResultSet;
use Porter\ExportModel;
use Porter\StorageInterface;

class Database implements StorageInterface
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
     * @var Connection
     */
    public Connection $connection;

    /**
     * @param Connection $c
     */
    public function __construct(Connection $c)
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
     * @return int Count of how many records were stored.
     */
    public function store(
        string $name,
        array $map,
        array $structure,
        $data,
        array $filters,
        ExportModel $exportModel
    ): int {
        $rowCount = 0;
        $batchedValues = [];
        $db = $this->connection->dbm->getConnection($this->connection->getAlias());

        if (is_a($data, '\Porter\Database\ResultSet')) {
            // Iterate on old ResultSet.
            while ($row = $data->nextResultRow()) {
                $rowCount++;
                $batchedValues[] = $exportModel->normalizeRow($map, $structure, $row, $filters);

                // Insert batched records and reset batch.
                if (self::INSERT_BATCH === count($batchedValues)) {
                    $db->table($this->prefix . $name)->insert($batchedValues);
                    $batchedValues = [];
                }
            }
        } elseif (is_a($data, '\Illuminate\Database\Query\Builder')) {
            // Use the Builder to process results one at a time.
            foreach ($data->cursor() as $row) { // Using `chunk()` takes MUCH longer to process.
                $rowCount++;
                $batchedValues[] = $exportModel->normalizeRow($map, $structure, (array)$row, $filters);

                // Insert batched records and reset batch.
                if (self::INSERT_BATCH === count($batchedValues)) {
                    $db->table($this->prefix . $name)->insert($batchedValues);
                    $batchedValues = [];
                }
            }
        }

        // Insert remaining records.
        $db->table($this->prefix . $name)->insert($batchedValues);

        return $rowCount;
    }

    /**
     * Create fresh table for storage. Use prefix.
     *
     * @param string $name
     * @param array $structure
     */
    public function prepare(string $name, array $structure): void
    {
        $this->createTable($this->prefix . $name, $this->getTableStructureClosure($structure));
    }

    /**
     * Create a new table. Drops the table first if it already exists.
     *
     * @param string $name
     * @param callable $closure
     */
    public function createTable(string $name, callable $closure)
    {
        $schema = $this->connection->dbm->getConnection($this->connection->getAlias())->getSchemaBuilder();
        $schema->dropIfExists($name);
        $schema->create($name, $closure);
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
     * Required by interface.
     */
    public function begin()
    {
        // Do nothing.
    }

    /**
     * Required by interface.
     */
    public function end()
    {
        // Do nothing.
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
