<?php
// Values here are overridden by CLI inputs.
return [
    // Package names (e.g. 'Xenforo').
    'source' => '',
    'target' => '',

    // Database table prefixes (leave blank for default).
    'source_prefix' => '',
    'target_prefix' => '',

    // Paths to local folders (optional, for if files need renaming).
    // If the platform uses subfolders for thumbnails, the package should figure that out.
    'source_attachments' => '~/source/files',
    'target_attachments' => '~/target/files',
    'source_avatars' => '~/source/avatars',
    'target_avatars' => '~/target/avatars',

    // Relative web path prefixed to attachments / files in the database (for links).
    // Ex: if your imported attachments will be at https://example.com/uploads/imported/{filename},
    //  then your target_webroot is 'uploads/imported/'.
    // With these default settings, you would copy the entire folders `~/target/avatars`
    //  and `~/target/attachments` into your forum's `uploads` folder after migrating.
    'option_attachments_webroot' => '', // Flarum: 'uploads/files/',
    'option_avatars_webroot' => '', // Flarum: 'uploads/avatars/',

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
