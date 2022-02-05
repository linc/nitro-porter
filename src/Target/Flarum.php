<?php

namespace Porter\Target;

use Porter\Target;
use Porter\ImportModel;

class Flarum extends Target
{
    public const SUPPORTED = [
        'name' => 'Flarum',
        'prefix' => 'FLA_',
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 0,
            'PrivateMessages' => 0,
        ]
    ];

    /**
     * Main import process.
     */
    public function import(string $tableName, array $structure, object $data, array $map = []): int
    {
        //$this->users();
        //$this->roles(); // Groups
        //$this->categories(); // Tags
        //$this->discussions();
        //$this->comments(); // Posts
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
