<?php
// Values here are overridden by CLI inputs.
return [
    // Package names (e.g. 'Xenforo').
    'source' => '',
    'target' => '',

    // Database table prefixes (leave blank for default).
    'source_prefix' => '',
    'target_prefix' => '',

    // Paths to folders (optional).
    'source_attachments' => '',
    'target_attachments' => '',
    'source_avatars' => '',
    'target_avatars' => '',

    // Aliases of connections.
    // (If you're just editing the 2 default connections below, don't change these.)
    'input_alias' => 'input',
    'output_alias' => 'output',

    // Data connections.
    'connections' => [
        [
            'alias' => 'input',
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
            'alias' => 'output',
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

    // Advanced options.
    'option_cdn_prefix' => '',
    'option_data_types' => '',
    'debug' => false,
    'test_alias' => 'test',
];
