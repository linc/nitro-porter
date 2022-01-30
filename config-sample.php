<?php

return [
    'debug' => false,
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
            'charset' => 'utf8',
        ],
        [
            'alias' => 'vanilla-csv',
            'type' => 'files',
            'path' => '',
        ],
        [
            'alias' => 'avatars-source',
            'type' => 'files',
            'path' => '',
        ],
        [
            'alias' => 'avatars-target',
            'type' => 'files',
            'path' => '',
        ],
        [
            'alias' => 'attach-source',
            'type' => 'files',
            'path' => '',
        ],
        [
            'alias' => 'attach-target',
            'type' => 'files',
            'path' => '',
        ],
    ],
    'test_connections' => [
       [
           'alias' => 'test',
           'type' => 'database',
           'adapter' => 'mysql',
           'host' => '127.0.0.1',
           'port' => '3306',
           'name' => 'testing_db',
           'user' => 'root',
           'pass' => 'password_here',
           'charset' => 'utf8',
       ],
    ],
];
