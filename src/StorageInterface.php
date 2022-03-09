<?php

namespace Porter;

interface StorageInterface
{
    /**
     * Software-specific import process.
     *
     * @param string $name Name of the data chunk / table to be written.
     * @param array $structure
     * @param object $data
     * @param array $map
     * @param array $filter
     * @return int Count of imported records.
     */
    public function store(string $name, array $structure, object $data, array $map = [], array $filter = []): int;

    /**
     * @param string $name
     * @param array $structure The final, combined structure to be written.
     */
    public function prepare(string $name, array $structure): void;

    public function begin();

    public function end();
}
