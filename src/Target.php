<?php

namespace Porter;

abstract class Target
{
    public const SUPPORTED = [
        'name' => '',
        'prefix' => '',
        'charset_table' => '',
        'features' => [],
    ];

    /**
     * Register supported features.
     */
    public static function getSupport(): array
    {
        return static::SUPPORTED;
    }

    /**
     * Software-specific import process.
     *
     * @param string $tableName
     * @param array $structure
     * @param object $data
     * @param array $map
     * @return int Count of imported records.
     */
    abstract public function import(string $tableName, array $structure, object $data, array $map = []): int;

    abstract public function begin();

    abstract public function end();
}
