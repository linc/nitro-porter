<?php

return
[
    'paths' => [
        'migrations' => __DIR__ . '/db/migrations',
        'seeds' => __DIR__ . '/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'testing',
        'testing' => getTestDatabaseCredentials(),
    ],
    'version_order' => 'creation'
];
