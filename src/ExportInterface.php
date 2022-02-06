<?php

namespace Porter;

interface ExportInterface
{
    /**
     * Software-specific import process.
     *
     * @param string $tableName
     * @param array $structure
     * @param object $data
     * @param array $map
     * @return int Count of imported records.
     */
    public function output(string $tableName, array $structure, object $data, array $map = []): int;

    public function begin();

    public function end();
}
