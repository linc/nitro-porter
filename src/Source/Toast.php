<?php

/**
 * Toast (.NET) exporter tool
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class Toast extends Source
{
    public const SUPPORTED = [
        'name' => 'Toast',
        'prefix' => 'tstdb_',
        'charset_table' => 'Post',
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
     * Main export method.
     *
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
            'ID' => 'UserID',
            'Username' => 'Name',
            'Email' => 'Email',
            'LastLoginDate' => array('Column' => 'DateLastActive', 'Type' => 'datetime'),
            'IP' => 'LastIPAddress'
        );
        $ex->export(
            'User',
            "select *, NOW() as DateInserted from :_Member u",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        // Determine safe RoleID to use for non-existant Member role
        $lastRoleID = 1001;
        $lastRoleResult = $ex->query("select max(ID) as LastID from :_Group");
        if ($lastRole = $lastRoleResult->nextResultRow()) {
            $lastRoleID = $lastRole['LastID'] + 1;
        }

        // Add default Member role.
        $role_Map = array(
            'ID' => 'RoleID',
            'Name' => 'Name'
        );
        $ex->export(
            'Role',
            " select ID, Name from :_Group
                union all
                select $lastRoleID as ID, 'Member' as Name from :_Group;",
            $role_Map
        );

        // UserRole.
        // Users without roles get put into new Member role.
        $userRole_Map = array(
            'MemberID' => 'UserID',
            'GroupID' => 'RoleID'
        );
        $ex->export(
            'UserRole',
            " select GroupID, MemberID from :_MemberGroupLink
                 union all
                 select
                    $lastRoleID as GroupID,
                    m.ID as MemberID
                 from :_Member m
                 left join :_MemberGroupLink l
                    on l.MemberID = m.ID
                 where l.GroupID is null",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function signatures(ExportModel $ex): void
    {
        $ex->export(
            'UserMeta',
            " select
                    ID as UserID,
                    'Plugin.Signatures.Sig' as `Name`,
                    Signature as `Value`
                 from :_Member
                 where Signature <> ''
                 union all
                 select
                    ID as UserID,
                    'Plugin.Signatures.Format' as `Name`,
                    'BBCode' as `Value`
                 from :_Member
                 where Signature <> '';"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array(
            'ID' => 'CategoryID',
            'CategoryID' => 'ParentCategoryID',
            'ForumName' => 'Name',
            'Description' => 'Description'
        );
        $ex->export(
            'Category',
            "select
                    f.ID,
                    f.CategoryID * 1000 as CategoryID,
                    f.ForumName,
                    f.Description
                from :_Forum f
                union all
                select
                    c.ID * 1000 as ID,
                    -1 as CategoryID,
                    c.Name as ForumName,
                    null as Description
                from :_Category c;",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
            'ID' => 'DiscussionID',
            'ForumID' => 'CategoryID',
            'MemberID' => 'InsertUserID',
            'PostDate' => 'DateInserted',
            'ModifyDate' => 'DateUpdated',
            'LastPostDate' => 'DateLastComment',
            'Subject' => 'Name',
            'Message' => 'Body',
            'Hits' => 'CountViews',
            'ReplyCount' => 'CountComments'
        );
        $ex->export(
            'Discussion',
            "select p.*,
            'Html' as Format
                from :_Post p
                where p.Topic = 1
                    and p.Deleted = 0;",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $comment_Map = array(
            'ID' => 'CommentID',
            'TopicID' => 'DiscussionID',
            'MemberID' => 'InsertUserID',
            'PostDate' => 'DateInserted',
            'ModifyDate' => 'DateUpdated',
            'Message' => 'Body'
        );
        $ex->export(
            'Comment',
            "select *,
                    'Html' as Format
                from :_Post p
                where Topic = 0 and Deleted = 0;",
            $comment_Map
        );
    }
}
