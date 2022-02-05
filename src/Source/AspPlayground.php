<?php

/**
 * ASP Playground exporter tool
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class AspPlayground extends Source
{
    public const SUPPORTED = [
        'name' => 'ASP Playground',
        'prefix' => 'pgd_',
        'charset_table' => 'Threads',
        'options' => [
        ],
        'features' => [
            'Users' => 1,
            'Passwords' => 0,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
        ]
    ];

    /**
     * @param ExportModel $ex
     */
    public function run($ex)
    {
        $this->users($ex);
        $this->roles($ex);
        $this->signatures($ex);
        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $user_Map = array(
            'Mem' => 'UserID',
            'Login' => 'Name',
            'Email' => 'Email',
            'Userpass' => 'Password',
            'totalPosts' => 'CountComments',
            'ip' => 'LastIPAddress',
            'banned' => 'Banned',
            'dateSignUp' => 'DateInserted',
            'lastLogin' => 'DateLastActive',
        );
        $ex->export(
            'User',
            "
         select m.*,
            'Text' as HashMethod
         from :_Members m;",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        // Make everyone a member since there's no used roles.
        $userRole_Map = array(
            'Mem' => 'UserID'
        );
        $ex->export('UserRole', 'select Mem, 8 as RoleID from :_Members', $userRole_Map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function signatures(ExportModel $ex): void
    {
        $ex->export(
            'UserMeta',
            "
         select
            Mem,
            'Plugin.Signatures.Sig' as `Name`,
            signature as `Value`
         from :_Members
         where signature <> ''

         union all

         select
            Mem,
            'Plugin.Signatures.Format' as `Name`,
            'BBCode' as `Value`
         from :_Members
         where signature <> '';"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array(
            'ForumID' => 'CategoryID',
            'ForumTitle' => 'Name',
            'ForumDesc' => 'Description',
            'Sort' => 'Sort',
            'lastModTime' => 'DateUpdated'
        );
        $ex->export(
            'Category',
            "select f.*
                from :_Forums f;",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
            'messageID' => 'DiscussionID',
            'ForumID' => 'CategoryID',
            'mem' => 'InsertUserID',
            'dateCreated' => 'DateInserted',
            'Subject' => 'Name',
            'hits' => 'CountViews',
            'lastupdate' => 'DateLastComment'
        );
        $ex->export(
            'Discussion',
            "select t.*, m.Body
                from :_Threads t
                left join :_Messages m on m.messageID = t.messageID;",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $comment_Map = array(
            'messageID' => 'CommentID',
            'threadID' => 'DiscussionID',
            'parent' => array('Column' => 'ReplyToCommentID', 'Type' => 'int'),
            'Mem' => 'InsertUserID',
            'dateCreated' => 'DateInserted',
            'Body' => 'Body',
            'ip' => 'InsertIPAddress'
        );
        $ex->export(
            'Comment',
            "select m.*, 'BBCode' as Format
                from :_Messages m;",
            $comment_Map
        );
    }
}