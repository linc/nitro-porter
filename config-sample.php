<?php

return [
    'debug' => false,
    'connections' => [
        'databases' => [
            [
                'alias' => 'source',
                'adapter' => 'mysql',
                'host' => 'localhost',
                'port' => '3306',
                'name' => 'some_db_name',
                'user' => 'root',
                'pass' => 'password_here',
                'charset' => 'utf8',
            ],
        ],
        'files' => [
            [
                'alias' => 'vanilla-csv',
                'path' => '',
            ],
            [
                'alias' => 'avatars-source',
                'path' => '',
            ],
            [
                'alias' => 'avatars-target',
                'path' => '',
            ],
            [
                'alias' => 'attach-source',
                'path' => '',
            ],
            [
                'alias' => 'attach-target',
                'path' => '',
            ],
        ]
    ],
    'test_connections' => [
       'databases' => [
           [
                'alias' => 'test',
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
