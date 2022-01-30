<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author YOUR NAME
 */

namespace Porter\Package;

use Porter\ExportController;
use Porter\ExportModel;

class Flarum extends ExportController
{
    public const SUPPORTED = [
        'name' => 'Flarum',
        'prefix' => 'FLA_',
        'charset_table' => 'posts',
        'options' => [
        ],
        'features' => [  // Set features you support to 1 or a string (for support notes).
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 0,
            'PrivateMessages' => 0,
            'Signatures' => 0,
            'Attachments' => 0,
            'Bookmarks' => 0,
            'Permissions' => 0,
            'Badges' => 0,
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 0,
            'Reactions' => 0,
            'Articles' => 0,
        ]
    ];

    /**
     * @var array Required tables => columns
     */
    protected $sourceTables = [
        'discussions' => [],
        'groups' => [],
        'posts' => [],
        'tags' => [],
        'users' => [],
    ];

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport(ExportModel $ex)
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
        $user_Map = [
            'id' => 'UserID',
            'username' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'email' => 'Email',
            'password' => 'Password',
            'joined_at' => 'DateInserted',
            'last_seen_at' => 'DateLastActive',
            'is_email_confirmed' => 'Confirmed',
            'discussion_count' => 'CountDiscussions',
            'comment_count' => 'CountComments',
        ];
        $ex->exportTable(
            'User',
            "select *, 'phpass' as HashMethod from :_users",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $role_Map = array(
            'id' => 'RoleID',
            'name_singular' => 'Name',
        );
        $ex->exportTable(
            'Role',
            "select * from :_groups",
            $role_Map
        );

        // User Role.
        $userRole_Map = [
            'user_id' => 'UserID',
            'group_id' => 'RoleID',
        ];
        $ex->exportTable(
            'UserRole',
            "select * from :_group_user",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = [
            'id' => 'CategoryID',
            'name' => 'Name',
            'slug' => 'UrlCode',
            'description' => 'Description',
            'parent_id' => 'ParentCategoryID',
            'position' => 'Sort',
            'discussion_count' => 'CountDiscussions',
        ];
        $ex->exportTable(
            'Category',
            "select * from :_tags",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
            'id' => 'DiscussionID',
            'user_id' => 'InsertUserID',
            'title' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
        );
        $ex->exportTable(
            'Discussion',
            "select *, p.content as Body, dt.tag_id as CategoryID
                 from :_discussions d
                 join :_posts p
                    on p.id = d.first_post_id
                 join :_discussion_tag dt
                    on dt.discussion_id = d.id",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $comment_Map = [
            'id' => 'CommentID',
            'discussion_id' => 'DiscussionID',
            'user_id' => 'InsertUserID',
            'created_at' => 'DateInserted',
            'edited_at' => 'LastUpdated',
            'edited_user_id' => 'UpdateUserID',
        ];
        $ex->exportTable(
            'Comment',
            "select *, 'Html' as Format
                from :_posts
                where type = 'comment'
                    and `number` > 1",
            $comment_Map
        );
    }
}
