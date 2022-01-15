<?php

return [
    'debug' => false,
    'connections' => [
        'databases' => [
            [
                'alias' => 'First Database',
                'adapter' => 'mysql',
                'host' => 'localhost',
                'port' => 3306,
                'name' => 'some_db_name',
                'user' => 'root',
                'pass' => 'password_here',
                'charset' => 'utf8',
            ],
        ],
    ],
    'test_connections' => [
       'databases' => [
           [
                'alias' => 'Test DB',
                'adapter' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'name' => 'testing_db',
                'user' => 'root',
                'pass' => 'password_here',
                'charset' => 'utf8',
           ],
        ],
    ],
];
