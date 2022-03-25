<?php

namespace Porter\Target;

use Porter\ExportModel;
use Porter\Target;

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
    public function run(ExportModel $ex)
    {
        $this->users($ex);
        $this->roles($ex); // Groups
        $this->categories($ex); // Tags
        $this->discussions($ex);
        $this->comments($ex); // Posts
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'username' => 'varchar(100)',
            'email' => 'varchar(100)',
            'is_email_comfirmed' => 'tinyint',
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
        $map = [
            'UserID' => 'id',
            'Name' => 'username',
            'Email' => 'email',
            'Password' => 'password',
            'DateInserted' => 'joined_at',
            'DateLastActive' => 'last_seen_at',
            'Confirmed' => 'is_email_confirmed',
            'CountDiscussions' => 'discussion_count',
            'CountComments' => 'comment_count',
        ];
        $ex->import(
            'users',
            $ex->dbImport()->table('PORT_User')->select('*'),
            $structure,
            $map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'name_singular' => 'varchar(100)',
        ];
        $map = array(
            'RoleID' => 'id',
            'Name' => 'name_singular',
        );
        $ex->import(
            'groups',
            $ex->dbImport()->table('PORT_Role')->select('*'),
            $structure,
            $map
        );

        // User Role.
        $structure = [
            'user_id' => 'int',
            'group_id' => 'int',
        ];
        $map = [
            'UserID' => 'user_id',
            'RoleID' => 'group_id',
        ];
        $ex->import(
            'group_user',
            $ex->dbImport()->table('PORT_UserRole')->select('*'),
            $structure,
            $map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'name' => 'varchar(100)',
            'slug' => 'varchar(100)',
            'description' => 'text',
            'parent_id' => 'int',
            'position' => 'int',
            'discussion_count' => 'int',
        ];
        $map = [
            'CategoryID' => 'id',
            'Name' => 'name',
            'UrlCode' => 'slug',
            'Description' => 'description',
            'ParentCategoryID' => 'parent_id',
            'Sort' => 'position',
            'CountDiscussions' => 'discussion_count',
        ];
        $ex->import(
            'tags',
            $ex->dbImport()->table('PORT_Category')->select('*'),
            $structure,
            $map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'user_id' => 'int',
            'title' => 'varchar(100)',
            'content' => 'longText',
            'tag_id' => 'int',
            'view_count' => 'int', // flarumite/simple-discussion-views
        ];
        $map = array(
            'DiscussionID' => 'id',
            'InsertUserID' => 'user_id',
            'Name' => 'title',
            'Body' => 'content',
            'CountViews' => 'view_count',
        );
        $ex->import(
            'discussions',
            $ex->dbImport()->table('PORT_Discussion')->select('*'),
            $structure,
            $map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'discussion_id' => 'int',
            'user_id' => 'int',
            'created_at' => 'datetime',
            'edited_at' => 'datetime',
            'edited_user_id' => 'int',
            'type' => 'varchar(100)',
        ];
        $map = [
            'CommentID' => 'id',
            'DiscussionID' => 'discussion_id',
            'InsertUserID' => 'user_id',
            'DateInserted' => 'created_at',
            'LastUpdated' => 'edited_at',
            'UpdateUserID' => 'edited_user_id',
        ];
        $ex->import(
            'posts',
            $ex->dbImport()->table('PORT_Comment')
                ->select('*', $ex->dbImport()->raw('"comment" as type')),
            $structure,
            $map
        );
    }
}
