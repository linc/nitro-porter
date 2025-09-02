<?php

return
[
    'paths' => [
        'migrations' => __DIR__ . '/tests/db/migrations',
        'seeds' => __DIR__ . '/tests/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'test',
        'test' => (new \Porter\ConnectionManager())->getAllInfo(),
    ],
    'version_order' => 'creation'
];
