<?php

namespace Porter\Storage;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Porter\Connection;
use Porter\StorageInterface;

class Database implements StorageInterface
{
    /**
     * @var array Table structures that define the format of the intermediary export tables.
     */
    public array $mapStructure = []; // @todo

    /**
     * @var string Prefix for the target database.
     */
    public string $destPrefix = 'PORT_';

    /**
     * @var string Name of target database.
     */
    public string $destDb = '';

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
     * @param string $name
     * @param array $structure
     * @param object $data
     * @param array $map
     * @param array $filter
     * @return int
     */
    public function store(string $name, array $structure, object $data, array $map = [], array $filter = []): int
    {
        //@todo I THINK THIS IS THE LAST PIECE OF GETTING DB STORAGE TO WORK?

        // Loop thru $data? Then within that run the filter:

        $row = [];
        $value = '';
        foreach ($map as $source => $dest) {
            // @todo
            // Check to see if there is a callback filter.
            if (isset($filter[$source])) {
                $value = $this->filter($source, $value, $row, $filter[$source]);
            }
        }

        // Batch inserts or we gonna die.


        return 0;
    }

    /**
     * Apply the data map's filter callback.
     *
     * @todo Abstract this for File storage too.
     * @see File::writeRow()
     */
    public function filter(string $field, $value, $data, $callback)
    {
        return call_user_func($callback, $value, $field, $data, $field);
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
        $this->connection->dbm->schema()->create($name, $closure);
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
                    $table->string($columnName, $length);
                } elseif (is_array($type)) {
                    // Handle enums.
                    $table->enum($columnName, $type); // $type == $options.
                } else {
                    // Handle everything else.
                    // But first, un-abbreviate 'int' (e.g. `bigint`, `tinyint(1)`).
                    $type = preg_replace('/int($|\()/', 'integer', $type);
                    $table->$type($columnName);
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
        return $this->connection->dbm->getConnection()->raw($sql);
    }
}
