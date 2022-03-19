<?php

namespace Porter\Storage;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Porter\Connection;
use Porter\Database\ResultSet;
use Porter\ExportModel;
use Porter\StorageInterface;
use Illuminate\Database\Capsule\Manager as Capsule;

class Database implements StorageInterface
{
    public const INSERT_BATCH = 1000;

    /**
     * @var array Table structures that define the format of the intermediary export tables.
     */
    public array $mapStructure = []; // @todo

    /**
     * @var string Prefix for the target database.
     */
    public string $destPrefix = 'PORT_';

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
     * Save the given records to the database.
     *
     * @param string $name
     * @param array $map
     * @param array $structure
     * @param ResultSet $data
     * @param array $filters
     * @param ExportModel $exportModel
     * @return int Count of how many records were stored.
     */
    public function store(
        string $name,
        array $map,
        array $structure,
        ResultSet $data,
        array $filters,
        ExportModel $exportModel
    ): int {
        $rowCount = 0;
        $batchedValues = [];
        while ($row = $data->nextResultRow()) {
            $rowCount++;
            $batchedValues[] = $exportModel->normalizeRow($map, $structure, $row, $filters);

            // Insert batched records and reset batch.
            if (self::INSERT_BATCH === count($batchedValues)) {
                $this->connection->dbm->getConnection($this->connection->getAlias())
                    ->table($name)->insert($batchedValues);
                $batchedValues = [];
            }
        }

        // Insert remaining records.
        $this->connection->dbm->getConnection($this->connection->getAlias())
            ->table($name)->insert($batchedValues);

        return $rowCount;
    }

    /**
     * Create fresh table for storage.
     *
     * @param string $name
     * @param array $structure
     */
    public function prepare(string $name, array $structure): void
    {
        $this->createTable($name, $this->getTableStructureClosure($structure));
    }

    /**
     * @param string $name
     * @param callable $closure
     */
    public function createTable(string $name, callable $closure)
    {
        $schema = $this->connection->dbm->getConnection($this->connection->getAlias())->getSchemaBuilder();
        // @todo DROP TABLE FIRST
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
                if (strpos($type, 'varchar') === 0) {
                    // Handle varchars.
                    $length = $this->getVarcharLength($type);
                    $table->string($columnName, $length)->nullable();
                } elseif (is_array($type)) {
                    // Handle enums.
                    $table->enum($columnName, $type)->nullable(); // $type == $options.
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
     * @param $type
     * @return array
     */
    public function getVarcharLength($type): int
    {
        $matches = [];
        preg_match('/varchar\(([0-9]){1,3}\)/', $type, $matches);
        return (int)$matches[1] ?? 100;
    }

    /**
     * @deprecated
     * @param string $sql
     * @return Expression
     */
    public function query(string $sql): Expression
    {
        return $this->connection->open()->raw($sql);
    }
}
