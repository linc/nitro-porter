<?php

/**
 * ASP Playground exporter tool
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Package;

use Porter\ExportController;
use Porter\ExportModel;

class AspPlayground extends ExportController
{
    public const SUPPORTED = [
        'name' => 'ASP Playground',
        'prefix' => 'pgd_',
        'options' => [
        ],
        'features' => [
            'Users' => 1,
            'Passwords' => 0,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 0,
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
     * @param ExportModel $ex
     */
    public function forumExport($ex)
    {
        $characterSet = $ex->getCharacterSet('Threads');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->beginExport('', 'ASP Playground');
        $ex->sourcePrefix = 'pgd_';

        $this->users($ex);

        $this->roles($ex);

        $this->signatures($ex);

        $this->categories($ex);

        $this->discussions($ex);

        $this->comments($ex);

        $ex->endExport();
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
        $ex->exportTable(
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
        $ex->exportTable('UserRole', 'select Mem, 8 as RoleID from :_Members', $userRole_Map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function signatures(ExportModel $ex): void
    {
        $ex->exportTable(
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

        $ex->exportTable(
            'Category',
            "
         select f.*
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
        $ex->exportTable(
            'Discussion',
            "
         select
            t.*,
            m.Body
         from :_Threads t
         left join :_Messages m on m.messageID = t.messageID
         ;",
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
        $ex->exportTable(
            'Comment',
            "
         select m.*,
            'BBCode' as Format
         from :_Messages m;",
            $comment_Map
        );
    }
}
