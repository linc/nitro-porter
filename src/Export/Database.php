<?php

namespace Porter\Export;

use Porter\ExportInterface;

class Database implements ExportInterface
{
    public function output(string $tableName, array $structure, object $data, array $map = []): int
    {
        return 0;
    }

    public function begin()
    {
        //
    }

    public function end()
    {
        //
    }
}
