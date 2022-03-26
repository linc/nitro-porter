<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author Lincoln Russell, lincolnwebs.com
 */

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

    protected const FLAGS = [
        'hasDiscussionBody' => false,
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
        $query = $ex->dbImport()->table('PORT_User')->select('*');

        $ex->import('users', $query, $structure, $map);
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
        $query = $ex->dbImport()->table('PORT_Role')->select('*');

        $ex->import('groups', $query, $structure, $map);

        // User Role.
        $structure = [
            'user_id' => 'int',
            'group_id' => 'int',
        ];
        $map = [
            'UserID' => 'user_id',
            'RoleID' => 'group_id',
        ];
        $query = $ex->dbImport()->table('PORT_UserRole')->select('*');

        $ex->import('group_user', $query, $structure, $map);
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
        $query = $ex->dbImport()->table('PORT_Category')->select('*');

        $ex->import('tags', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'user_id' => 'int',
            'title' => 'varchar(200)',
            'tag_id' => 'int',
            'view_count' => 'int', // flarumite/simple-discussion-views
        ];
        $map = array(
            'DiscussionID' => 'id',
            'InsertUserID' => 'user_id',
            'Name' => 'title',
            'CountViews' => 'view_count',
        );
        $query = $ex->dbImport()->table('PORT_Discussion')->select('*');

        $ex->import('discussions', $query, $structure, $map);
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
            'content' => 'longText',
        ];
        $map = [
            'CommentID' => 'id',
            'DiscussionID' => 'discussion_id',
            'InsertUserID' => 'user_id',
            'DateInserted' => 'created_at',
            'DateUpdated' => 'edited_at',
            'UpdateUserID' => 'edited_user_id',
            'Body' => 'content'
        ];
        $query = $ex->dbImport()->table('PORT_Comment')
            ->select(
                'CommentID',
                'DiscussionID',
                'InsertUserID',
                'DateInserted',
                'DateUpdated',
                'UpdateUserID',
                'Body',
                $ex->dbImport()->raw('"comment" as type')
            );

        // Extract OP from the discussion.
        if ($this->getDiscussionBodyMode()) {
            // Get highest CommentID.
            $result = $ex->dbImport()
                ->table('PORT_Comment')
                ->select($ex->dbImport()->raw('max(CommentID) as LastCommentID'))
                ->first();

            // Use DiscussionID but fast-forward it past highest CommentID to insure it's unique.
            $discussions = $ex->dbImport()->table('PORT_Discussion')
                ->select(
                    $ex->dbImport()->raw('(DiscussionID + ' . $result->LastCommentID . ') as CommentID'),
                    'DiscussionID',
                    'InsertUserID',
                    'DateInserted',
                    'DateUpdated',
                    'UpdateUserID',
                    'Body',
                    $ex->dbImport()->raw('"comment" as type')
                );

            // Combine discussions.body with the comments to get all posts.
            $query->union($discussions);
        }

        $ex->import('posts', $query, $structure, $map);
    }
}
