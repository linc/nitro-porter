<?php

return [
    'debug' => false,
    'test_alias' => 'test',
    'connections' => [
        [
            'alias' => 'source',
            'type' => 'database',
            'adapter' => 'mysql',
            'host' => 'localhost',
            'port' => '3306',
            'name' => 'some_db_name',
            'user' => 'root',
            'pass' => 'password_here',
            'charset' => 'utf8mb4',
            'options' => [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // Critical for large datasets.
            ],
        ],
        [
            'alias' => 'test',
            'type' => 'database',
            'adapter' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'name' => 'testing_db',
            'user' => 'root',
            'pass' => 'password_here',
            'charset' => 'utf8mb4',
            'options' => [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // Critical for large datasets.
            ],
        ],
    ],
];
