<?php
/**
 * Toast (.NET) exporter tool
 *
 * @copyright Vanilla Forums Inc. 2013
 * @license Proprietary
 * @package VanillaPorter
 */

$Supported['toast'] = array('name' => 'Toast', 'prefix' => 'tstdb_');
$Supported['toast']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Signatures' => 1,
    'Passwords' => 1,
);

class Toast extends ExportController {
    static $PasswordFormats = array(0 => 'md5', 1 => 'sha1', 2 => 'sha256', 3 => 'sha384', 4 => 'sha512');

    /**
     *
     * @param ExportModel $Ex
     */
    public function ForumExport($Ex) {

        $CharacterSet = $Ex->GetCharacterSet('Post');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        $Ex->BeginExport('', 'Toast Forum');
        $Ex->SourcePrefix = 'tstdb_';

        // User.
        $User_Map = array(
            'ID' => 'UserID',
            'Username' => 'Name',
            'Email' => 'Email',
            'LastLoginDate' => array('Column' => 'DateLastActive', 'Type' => 'datetime'),
            'IP' => 'LastIPAddress'
        );
        $Ex->ExportTable('User', "
         select
            *,
            NOW() as DateInserted
         from :_Member u", $User_Map);

        // Determine safe RoleID to use for non-existant Member role
        $LastRoleID = 1001;
        $LastRoleResult = $Ex->Query("select max(ID) as LastID from :_Group");
        if ($LastRoleResult) {
            $LastRole = mysql_fetch_array($LastRoleResult);
            $LastRoleID = $LastRole['LastID'] + 1;
        }

        // Role.
        // Add default Member role.
        $Role_Map = array(
            'ID' => 'RoleID',
            'Name' => 'Name'
        );
        $Ex->ExportTable('Role', "
         select
            ID,
            Name
         from :_Group

         union all

         select
            $LastRoleID as ID,
            'Member' as Name
         from :_Group;", $Role_Map);

        // UserRole.
        // Users without roles get put into new Member role.
        $UserRole_Map = array(
            'MemberID' => 'UserID',
            'GroupID' => 'RoleID'
        );
        $Ex->ExportTable('UserRole', "
         select
            GroupID,
            MemberID
         from :_MemberGroupLink

         union all

         select
            $LastRoleID as GroupID,
            m.ID as MemberID
         from :_Member m
         left join :_MemberGroupLink l
            on l.MemberID = m.ID
         where l.GroupID is null", $UserRole_Map);

        // Signatures.
        $Ex->ExportTable('UserMeta', "
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
        $Category_Map = array(
            'ID' => 'CategoryID',
            'CategoryID' => 'ParentCategoryID',
            'ForumName' => 'Name',
            'Description' => 'Description'
        );

        $Ex->ExportTable('Category', "
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
         from :_Category c;", $Category_Map);

        // Discussion.
        $Discussion_Map = array(
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
        $Ex->ExportTable('Discussion', "
         select p.*,
            'Html' as Format
         from :_Post p
         where p.Topic = 1
            and p.Deleted = 0;", $Discussion_Map);

        // Comment.
        $Comment_Map = array(
            'ID' => 'CommentID',
            'TopicID' => 'DiscussionID',
            'MemberID' => 'InsertUserID',
            'PostDate' => 'DateInserted',
            'ModifyDate' => 'DateUpdated',
            'Message' => 'Body'
        );
        $Ex->ExportTable('Comment', "
         select *,
            'Html' as Format
         from :_Post p
         where Topic = 0 and Deleted = 0;", $Comment_Map);


        $Ex->EndExport();
    }

    public function CleanDate($Value) {
        if (!$Value) {
            return null;
        }
        if (substr($Value, 0, 4) == '0000') {
            return null;
        }

        return $Value;
    }

}

?>
