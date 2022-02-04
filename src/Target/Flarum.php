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
     *
     * @param ImportModel $im
     */
    public function run(ImportModel $im)
    {
        //$this->users($im);
        //$this->roles($im); // Groups
        //$this->categories($im); // Tags
        //$this->discussions($im);
        //$this->comments($im); // Posts
    }
}
