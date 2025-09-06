<?php

/**
 *
 * @author Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class Flarum extends Source
{
    public const SUPPORTED = [
        'name' => 'Flarum',
        'prefix' => 'FLA_',
        'charset_table' => 'posts',
        'options' => [],
        'features' => [  // Set features you support to 1 or a string (for support notes).
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 0,
            'PrivateMessages' => 'fof/byobu',
            'Bookmarks' => 'flarum/subscriptions',
            'Badges' => '17development/flarum-user-badges',
        ]
    ];

    protected const FLAGS = [
        'hasDiscussionBody' => false,
    ];

    /**
     * @var array Required tables => columns
     */
    public array $sourceTables = [
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
    public function run(ExportModel $ex)
    {
        $this->users($ex);
        $this->roles($ex); // Groups
        $this->categories($ex); // Tags
        $this->discussions($ex);
        if ($ex->exists('discussion_user', ['subscription'])) {
            $this->bookmarks($ex); // flarum/subscriptions
        }
        $this->comments($ex); // Posts

        if ($ex->exists('badges')) {
            $this->badges($ex); // 17development/flarum-user-badges
        }
        if ($ex->exists('recipients')) {
            $this->privateMessages($ex); // fof/byobu
        }
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
        $ex->export(
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
        $ex->export(
            'Role',
            "select * from `:_groups`",
            $role_Map
        );

        // User Role.
        $userRole_Map = [
            'user_id' => 'UserID',
            'group_id' => 'RoleID',
        ];
        $ex->export(
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
        $ex->export(
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
            'is_sticky' => 'Announce', // flarum/sticky — optional field
            'is_locked' => 'Closed', // flarum/lock — optional field
        );

        $getBody = '';
        $joinPosts = '';
        if ($this->getDiscussionBodyMode()) {
            // Put the OP in the body.
            $getBody = 'p.content as Body,';
            $joinPosts = 'join :_posts p on p.id = d.first_post_id';
        }

        $ex->export(
            'Discussion',
            "select d.*, $getBody min(dt.tag_id) as CategoryID
                 from :_discussions d
                 $joinPosts
                 join :_discussion_tag dt on dt.discussion_id = d.id
                 where d.is_private <> 1
                 group by d.id",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function bookmarks(ExportModel $ex): void
    {
        $map = [
            'discussion_id' => 'DiscussionID',
            'user_id' => 'InsertUserID',
            'last_read_at' => 'DateLastViewed',
        ];
        $query = "select *, if (subscription = 'follow', 1, 0) as Bookmarked from :_discussion_user";

        $ex->export('UserDiscussion', $query, $map);
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
            'edited_at' => 'DateUpdated',
            'edited_user_id' => 'UpdateUserID',
            'content' => 'Body',
        ];

        $skipOP = '';
        if ($this->getDiscussionBodyMode()) {
            // Skip the OP.
            $skipOP = 'and `number` > 1';
        }

        $ex->export(
            'Comment',
            "select *, 'Html' as Format
                from :_posts
                where type = 'comment'
                    $skipOP",
            $comment_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function badges(ExportModel $ex): void
    {
        // Badges
        $map = [
            'discussion_id' => 'BadgeID',
            'user_id' => 'InsertUserID',
            'last_read_at' => 'DateLastViewed',
            'is_visible' => 'Visible',
        ];
        $query = "select * from :_badges";

        $ex->export('Badge', $query, $map);

        // User Badges
        $map = [
            'badge_id' => 'BadgeID',
            'user_id' => 'UserID',
            'description' => 'Reason',
            'assigned_at' => 'DateCompleted',
        ];
        $query = "select * from :_badge_user";

        $ex->export('UserBadge', $query, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function privateMessages(ExportModel $ex)
    {
        // Messages
        $map = [
            'discussion_id' => 'ConversationID',
            'content' => 'Body',
        ];
        $query = "select *
            from :_posts p
                left join :_discussions d on d.id = p.discussion_id
            where d.is_private = 1";

        $ex->export('ConversationMessage', $query, $map);

        // Conversations
        $map = [
            'discussion_id' => 'ConversationID',
            'user_id' => 'InsertUserID',
            'title' => 'Subject',
        ];
        $query = "select * from :_discussions where is_private = 1";

        $ex->export('Conversation', $query, $map);

        // Recipients
        $map = [
            'discussion_id' => 'ConversationID',
            'user_id' => 'UserID',
        ];
        $query = "select * from :_recipients";

        $ex->export('UserConversation', $query, $map);
    }
}
