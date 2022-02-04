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
     */
    abstract public function run(ImportModel $im);
}
