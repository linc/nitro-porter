<?php

/**
 * Q2A exporter tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Eduardo Casarero
 */

namespace Porter\Source;

use Porter\ExportModel;
use Porter\Source;

class Q2a extends Source
{
    public const SUPPORTED = [
        'name' => 'Questions2Answers',
        'prefix' => 'qa_',
        'charset_table' => 'posts',
        'options' => [
        ],
        'features' => [
            'Users' => 1,
            'Passwords' => 0,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 0,
            'PrivateMessages' => 0,
        ]
    ];

    /** @var array[] List of required tables. */
    public $sourceTables = array(
        'blobs' => array(),
        'categories' => array(),
        'posts' => array(),
        'users' => array(),
    );

    /**
     * Main export process.
     *
     * @param $ex
     */
    public function run($ex)
    {
        $this->users($ex);
        $this->roles($ex);
        $this->discussions($ex);
        $this->comments($ex);
    }

    /**
     * @param ExportModel $ex
     * @param array $user_Map
     */
    protected function users(ExportModel $ex): void
    {
        $ex->export(
            'User',
            "SELECT
                    u.userid as UserID,
                    u.handle as Name,
                    'Reset' as HashMethod,
                    u.email as Email,
                    u.created as DateInserted,
                    p.points as Points
                FROM :_users as u
                LEFT JOIN :_userpoints p USING(userid)
                WHERE u.userid IN (Select DISTINCT userid from :_posts)
                    AND (BIN(flags) & BIN(128) = 0) AND (BIN(flags) & BIN(2) = 0);"
        );
    }

    /**
     * @param $ex
     */
    protected function roles($ex): void
    {
        $ex->exportTable(
            'Role',
            "select 1 as RolesID, 'Member' as Name"
        );

        $ex->exportTable(
            'UserRole',
            "select
                    ur.userid as UserID,
                    1 as RoleID
                from :_users ur
                where (BIN(flags) & BIN(128) = 0) AND (BIN(flags) & BIN(2) = 0);"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $ex->export('Category', "select 1 as CategoryID, 'Legacy' as Name");
        $discussion_Map = array(
            'postid' => 'DiscussionID',
            'categoryid' => 'CategoryID',
            'userid' => 'InsertUserID',
            'Subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
        );
        $ex->export(
            'Discussion',
            "select
                    'Question' as Type,
                    p.postid as DiscussionID,
                    1 as CategoryID,
                    p.userid as InsertUserID,
                    LEFT(p.title,99) as Name,
                    'HTML' as Format,
                    p.content as Body,
                    p.created as DateInserted,
                    1 as Closed,
                    'Accepted' as QnA
                from :_posts p
                WHERE parentid IS NULL
                    AND userid IS NOT NULL
                    AND type = 'Q';",
            $discussion_Map
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
                    p.postid as CommentID,
                    p.parentid as DiscussionID,
                    p.userid as InsertUserID,
                    p.content as Body,
                    'HTML' as Format,
                    p.created as DateInserted
                from :_posts p
                WHERE type = 'A'
                    AND userid IS NOT NULL ;"
        );
    }
}
