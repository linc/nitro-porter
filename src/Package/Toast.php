<?php
/**
 * Toast (.NET) exporter tool
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author Lincoln Russell, lincolnwebs.com
 */

namespace NitroPorter\Package;

use NitroPorter\ExportController;

class Toast extends ExportController {

    const SUPPORTED = [
        'name' => 'Toast',
        'prefix' => 'tstdb_',
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

    public static $passwordFormats = array(0 => 'md5', 1 => 'sha1', 2 => 'sha256', 3 => 'sha384', 4 => 'sha512');

    /**
     *
     * @param ExportModel $ex
     */
    public function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('Post');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->beginExport('', 'Toast Forum');
        $ex->sourcePrefix = 'tstdb_';

        // User.
        $user_Map = array(
            'ID' => 'UserID',
            'Username' => 'Name',
            'Email' => 'Email',
            'LastLoginDate' => array('Column' => 'DateLastActive', 'Type' => 'datetime'),
            'IP' => 'LastIPAddress'
        );
        $ex->exportTable('User', "
         select
            *,
            NOW() as DateInserted
         from :_Member u", $user_Map);

        // Determine safe RoleID to use for non-existant Member role
        $lastRoleID = 1001;
        $lastRoleResult = $ex->query("select max(ID) as LastID from :_Group");
        if ($lastRole = $lastRoleResult->nextResultRow()) {
            $lastRoleID = $lastRole['LastID'] + 1;
        }

        // Role.
        // Add default Member role.
        $role_Map = array(
            'ID' => 'RoleID',
            'Name' => 'Name'
        );
        $ex->exportTable('Role', "
         select
            ID,
            Name
         from :_Group

         union all

         select
            $lastRoleID as ID,
            'Member' as Name
         from :_Group;", $role_Map);

        // UserRole.
        // Users without roles get put into new Member role.
        $userRole_Map = array(
            'MemberID' => 'UserID',
            'GroupID' => 'RoleID'
        );
        $ex->exportTable('UserRole', "
         select
            GroupID,
            MemberID
         from :_MemberGroupLink

         union all

         select
            $lastRoleID as GroupID,
            m.ID as MemberID
         from :_Member m
         left join :_MemberGroupLink l
            on l.MemberID = m.ID
         where l.GroupID is null", $userRole_Map);

        // Signatures.
        $ex->exportTable('UserMeta', "
         select
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
         where Signature <> '';");

        // Category.
        $category_Map = array(
            'ID' => 'CategoryID',
            'CategoryID' => 'ParentCategoryID',
            'ForumName' => 'Name',
            'Description' => 'Description'
        );

        $ex->exportTable('Category', "
         select
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
         from :_Category c;", $category_Map);

        // Discussion.
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
        $ex->exportTable('Discussion', "
         select p.*,
            'Html' as Format
         from :_Post p
         where p.Topic = 1
            and p.Deleted = 0;", $discussion_Map);

        // Comment.
        $comment_Map = array(
            'ID' => 'CommentID',
            'TopicID' => 'DiscussionID',
            'MemberID' => 'InsertUserID',
            'PostDate' => 'DateInserted',
            'ModifyDate' => 'DateUpdated',
            'Message' => 'Body'
        );
        $ex->exportTable('Comment', "
         select *,
            'Html' as Format
         from :_Post p
         where Topic = 0 and Deleted = 0;", $comment_Map);


        $ex->endExport();
    }

    public function cleanDate($value) {
        if (!$value) {
            return null;
        }
        if (substr($value, 0, 4) == '0000') {
            return null;
        }

        return $value;
    }

}

// Closing PHP tag required. (make.php)
?>
