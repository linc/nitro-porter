<?php

/**
 * Codoforum exporter tool. Tested with CodoForum v3.7.
 *
 * @author  Hans Adema
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class CodoForum extends Source
{
    public const SUPPORTED = [
        'name' => 'CodoForum',
        'prefix' => 'codo_',
        'charset_table' => 'posts',
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
            'Signatures' => 1,
        ]
    ];

    /**
     * @var array Required tables => columns
     */
    public array $sourceTables = array(
        'users' => array('id', 'username', 'mail', 'user_status', 'pass', 'signature'),
        'roles' => array('rid', 'rname'),
        'user_roles' => array('uid', 'rid'),
        'categories' => array('cat_id', 'cat_name'),
        'topics' => array('topic_id', 'cat_id', 'uid', 'title'),
        'posts' => array('post_id', 'topic_id', 'uid', 'imessage'),
    );

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see   $_structures in ExportModel for allowed destination tables & columns.
     */
    public function run($ex)
    {
        $this->users($ex);
        $this->roles($ex);
        $this->userMeta($ex);
        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $ex->export(
            'User',
            "select
                u.id as UserID,
                u.username as Name,
                u.mail as Email,
                u.user_status as Verified,
                u.pass as Password,
                'Vanilla' as HashMethod,
                from_unixtime(u.created) as DateFirstVisit
            from :_users u"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $ex->export(
            'Role',
            "select
                    r.rid as RolesID,
                    r.rname as Name
                from :_roles r"
        );

        // User Role.
        $ex->export(
            'UserRole',
            "select
                    ur.uid as UserID,
                    ur.rid as RoleID
                from :_user_roles ur
                where ur.is_primary = 1"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function userMeta(ExportModel $ex): void
    {
        $ex->export(
            'UserMeta',
            "select
                    u.id as UserID,
                    'Plugin.Signatures.Sig' as Name,
                    u.signature as Value
                from :_users u
                where u.signature != '' and u.signature is not null"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $ex->export(
            'Category',
            "select
                    c.cat_id as CategoryID,
                    c.cat_name as Name
                from :_categories c"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $ex->export(
            'Discussion',
            "select
                t.topic_id as DiscussionID,
                t.cat_id as CategoryID,
                t.uid as InsertUserID,
                t.title as Name,
                from_unixtime(t.topic_created) as DateInserted,
                from_unixtime(t.last_post_time) as DateLastComment
            from :_topics t"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $ex->export(
            'Comment',
            "select
                    p.post_id as CommentID,
                    p.topic_id as DiscussionID,
                    p.uid as InsertUserID,
                    p.imessage as Body,
                    'Markdown' as Format,
                    from_unixtime(p.post_created) as DateInserted
                from :_posts p"
        );
    }
}
