<?php

/**
 * Q2A exporter tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Eduardo Casarero
 */

namespace NitroPorter\Package;

use NitroPorter\ExportController;

class Q2a extends ExportController
{

    public const SUPPORTED = [
        'name' => 'Questions2Answers',
        'prefix' => 'qa_',
        'CommandLine' => [
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

    protected $sourceTables = array(
        'blobs' => array(),
        'categories' => array(),
        'posts' => array(),
        'users' => array(),
    );

    public function forumExport($ex)
    {
        $characterSet = $ex->getCharacterSet('posts');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }
        $ex->beginExport('', 'Questions2Answers');
        $user_Map = array();

        $ex->exportTable(
            'User',
            "
            SELECT
                u.userid as UserID,
                u.handle as Name,
                'Reset' as HashMethod,
                u.email as Email,
                u.created as DateInserted,
                p.points as Points
            FROM :_users as u
            LEFT JOIN :_userpoints p USING(userid)
            WHERE u.userid IN (Select DISTINCT userid from :_posts)
                AND (BIN(flags) & BIN(128) = 0) AND (BIN(flags) & BIN(2) = 0);
         ",
            $user_Map
        );

        $ex->exportTable(
            'Role',
            "
        select
            1 as RolesID,
            'Member' as Name
        "
        );

        $ex->exportTable(
            'UserRole',
            "
            select
                ur.userid as UserID,
                1 as RoleID
            from :_users ur
            where (BIN(flags) & BIN(128) = 0) AND (BIN(flags) & BIN(2) = 0);
        "
        );

        $ex->exportTable('Category', "select 1 as CategoryID, 'Legacy' as Name");
        $discussion_Map = array(
            'postid' => 'DiscussionID',
            'categoryid' => 'CategoryID',
            'userid' => 'InsertUserID',
            'Subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
        );
        $ex->exportTable(
            'Discussion',
            "
            select
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
             WHERE     parentid IS NULL
             AND userid IS NOT NULL
             AND type = 'Q';
         "
        );

        $ex->exportTable(
            'Comment',
            "
        select
            p.postid as CommentID,
            p.parentid as DiscussionID,
            p.userid as InsertUserID,
            p.content as Body,
            'HTML' as Format,
            p.created as DateInserted
            from :_posts p
        WHERE type = 'A'
            AND userid IS NOT NULL ;
        "
        );
        $ex->endExport();
    }
}
