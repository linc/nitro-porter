<?php

namespace Porter;

use Illuminate\Database\Query\Builder;
use Porter\Database\ResultSet;

abstract class Storage
{
    /**
     * Software-specific import process.
     *
     * @param string $name Name of the data chunk / table to be written.
     * @param array $map
     * @param array $structure
     * @param ResultSet|Builder $data
     * @param array $filters
     * @param ExportModel $exportModel
     * @return array Information about the results.
     */
    abstract public function store(
        string $name,
        array $map,
        array $structure,
        $data,
        array $filters,
        ExportModel $exportModel
    ): array;

    /**
     * @param string $name
     * @param array $structure The final, combined structure to be written.
     */
    abstract public function prepare(string $name, array $structure): void;

    abstract public function begin();

    abstract public function end();

    abstract public function setPrefix(string $prefix): void;

    abstract public function exists(string $tableName, array $columns = []): bool;
}
