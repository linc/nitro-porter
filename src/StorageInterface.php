<?php

namespace Porter;

use Illuminate\Database\Query\Builder;
use Porter\Database\ResultSet;

interface StorageInterface
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
     * @return int Count of imported records.
     */
    public function store(
        string $name,
        array $map,
        array $structure,
        $data,
        array $filters,
        ExportModel $exportModel
    ): int;

    /**
     * @param string $name
     * @param array $structure The final, combined structure to be written.
     */
    public function prepare(string $name, array $structure): void;

    public function begin();

    public function end();

    public function setPrefix(string $prefix): void;
}
