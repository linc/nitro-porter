<?php
/**
 * MVC exporter tool.
 *
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GNU GPL2
 * @package VanillaPorter
 * @see functions.commandline.php for command line usage.
 */

$supported['mvc'] = array('name' => 'mvc', 'prefix' => 'mvc_');
$supported['mvc']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Badge,' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'Attachments' => 0,
    'Signatures' => 1,
    'Tags' => 1,
);

class MVC extends ExportController {
    /**
     * You can use this to require certain tables and columns be present.
     *
     * @var array Required tables => columns
     */
    protected $sourceTables = array(
        'MembershipUser' => array(),
    );

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex)
    {

        $characterSet = $ex->getCharacterSet('posts');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Reiterate the platform name here to be included in the porter file header.
        $ex->beginExport('', 'MVC');

        $structures = $ex->structures();
        $structures['Badge'] = array(
            'BadgeID' => 'int',
            'Name' => 'varchar(64)',
            'Slug' => 'varchar(32)',
            'Type' => 'varchar(20)',
            'Body' => 'Text',
            'Photo' => 'varchar(255)',
            'Points' => 'int',
            'Active' => 'tinyint',
            'Visible' => 'tinyint',
            'Secret' => 'tinyint',
            'CanDelete' => 'tinyint',
            'DateInserted' => 'datetime',
            'DateUpdated' => 'datetime',
            'InsertUserID' => 'int',
            'UpdateUser' => 'int',
            'CountRecipients' => 'int',
            'Threshold' => 'int',
            'Class' => 'varchar(20)',
            'Level' => 'smallint',
            'Attributes' => 'text'
        );

        $structures['UserBadge'] = array(
            'UserID' => 'int',
            'BadgeID' => 'int',
            'Attributes' => 'text',
            'Reason' => 'varchar(255)',
            'ShowReason' => 'tinyint',
            'DateRequested' => 'datetime',
            'RequestReason' => 'varchar(255)',
            'Declined' => 'tinyint',
            'Count' => 'int',
            "DateCompleted" => 'datetime',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int'
        );

        $ex->structures($structures);

        // Users.
        $user_Map = array();

        if(!$ex->tableExists("UserId")){
            $ex->query("create table UserId as (
                    select Id from MembershipUser)
            ");

            $ex->query("ALTER TABLE UserId ADD VanillaID INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }

        $ex->exportTable('User', "
            select
                u.VanillaID as UserId,
                m.UserName as Name,
                'Reset' as HashMethod,
                m.Email as Email,
                m.Avatar as Photo,
                m.CreateDate as DateInserted,
                m.LastLoginDate as DateLastVisit,
                m.LastActivityDate as DateLastActive,
                m.IsBanned as Banned,
                m.Location as Location
            from MembershipUser m, UserId u

            where u.Id = m.Id
         ", $user_Map);

        // UserMeta.
        $ex->exportTable('UserMeta', "
            select
                u.VanillaID as UserID,
                'Website' as `Name`,
                m.Website as `Value`
            from MembershipUser m, UserId u
            where m.Website <> '' and u.Id = m.Id

            union

            select
                u.VanillaID as UserID,
                'Signatures.Sig',
                m.Signature
            from MembershipUser m, UserId u
            where m.Signature <> '' and u.Id = m.Id

        ");

        // Role.
        $role_Map = array();

        if(!$ex->tableExists("RoleId")){
            $ex->query("create table RoleId as (
                    select Id from MembershipRole)
            ");

            $ex->query("ALTER TABLE RoleId ADD VanillaID INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }

        $ex->exportTable('Role', "
            select
                r.VanillaID as RoleID,
                m.RoleName as Name
            from MembershipRole m, RoleId r
            where r.Id = m.Id
         ", $role_Map);

        // User Role.
        $userRole_Map = array();
        $ex->exportTable('UserRole', "
            select
                u.VanillaID as UserID,
                r.VanillaID as RoleID
            from MembershipUsersInRoles m, RoleId r, UserId u
            where r.Id = m.RoleIdentifier and u.Id = m.UserIdentifier
        ", $userRole_Map);

        //Badge.
        $badge_Map = array();

        if(!$ex->tableExists("BadgeId")){
            $ex->query("create table BadgeId as (
                    select Id from Badge)
            ");

            $ex->query("ALTER TABLE BadgeId ADD VanillaID INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }

        $ex->exportTable('Badge', "
            select
                b.VanillaID as BadgeID,
                m.Type as Type,
                m.DisplayName as Name,
                m.Description as Body,
                m.Image as Photo,
                m.AwardsPoints as Points
            from Badge m, BadgeId b
            where b.Id = m.Id
        ", $badge_Map);

        $user_badge_Map = array();
        $ex->exportTable('UserBadge', "
            select
                u.VanillaID as UserID,
                b.VanillaID as BadgeID,
                '' as Status,
                now() as DateInserted
            from MembershipUser_Badge m, UserId u, BadgeId b
            where u.Id = m.MembershipUser_Id and b.Id = m.Badge_Id
        ", $user_badge_Map);

        // Category.
        $category_Map = array();

        if(!$ex->tableExists("CategoryId")){
            $ex->query("create table CategoryId as (
                    select Id from :_Category)
            ");

            $ex->query("ALTER TABLE CategoryId ADD VanillaID INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }

        $ex->exportTable('Category', "

                          select
                c.VanillaID as CategoryID,
                p.VanillaID as ParentCategoryID,
                m.Name as Name,
                m.Description as Description,
                m.DateCreated as DateInserted,
                null as Sort
            from Category m, CategoryId c, CategoryId p
            where m.Category_Id <> '' and c.Id = m.Id and p.Id = m.Category_Id

            UNION

            select
                c.VanillaID as CategoryID,
                '-1' as ParentCategoryID,
                m.Name as Name,
                m.Description as Description,
                m.DateCreated as DateInserted,
                null as Sort
            from Category m, CategoryId c
            where m.Category_Id = '' and c.Id = m.Id


        ", $category_Map);

        // Discussion.
        $discussion_Map = array();

        if(!$ex->tableExists("DiscussionId")){
            $ex->query("create table DiscussionId as (
                    select Id from :_Topic)
            ");

            $ex->query("ALTER TABLE DiscussionId ADD VanillaID INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }

        $ex->exportTable('Discussion', "
            select
                d.VanillaID as DiscussionID,
                c.VanillaID as CategoryID,
                u.VanillaID as InsertUserID,
                m.CreateDate as DateInserted,
                m.Name as Name,
                m.Views as CountViews,
                'Html' as Format
            from Topic m, DiscussionId d, CategoryId c, UserId u
            where d.Id = m.Id and c.Id = m.Category_Id and u.Id = m.MembershipUser_Id

            ", $discussion_Map);

        // Comment.
        $comment_Map = array();

        if(!$ex->tableExists("CommentId")){
            $ex->query("create table CommentId as (
                    select Id from Post)
            ");

            $ex->query("ALTER TABLE CommentId ADD VanillaID INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }

        $ex->exportTable('Comment', "
            select
                c.VanillaID as CommentID,
                d.VanillaID as DiscussionID,
                u.VanillaID as InsertUserID,
                m.PostContent as Body,
                m.DateCreated as DateInserted,
                m.DateEdited as DateUpdated,
                'Html' as Format
            from Post m, CommentId c, DiscussionId d, UserId u
            where c.Id = m.Id and d.Id = m.Topic_Id and u.Id = m.MembershipUser_Id
         ", $comment_Map);

        // Tag
        $tag_Map = array();

        if(!$ex->tableExists("TagId")){
            $ex->query("create table TagId as (
                    select Id from TopicTag)
            ");

            $ex->query("ALTER TABLE TagId ADD VanillaID INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }

        $ex->exportTable('Tag', "
            select
                t.VanillaID as TagID,
                m.Tag as Name,
                m.Tag as FullName,
                now() as DateInserted
            from TopicTag m, TagId t
            where t.Id = m.Id
         ", $tag_Map);

        //Attachment WIP
        /*
        $attachment_Map = array();

        if(!$ex->tableExists("MediaId")){
            $ex->query("create table MediaId as (
                    select Id from UploadedFile)
            ");

            $ex->query("ALTER TABLE MediaId ADD VanillaID INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }

        // Use of placeholder for Type and Size due to lack of data in db. Will require external script to get the info.
        $ex->exportTable('Attachment', "
            select
                m.VanillaID  as MediaID,
                u.Filename as Name,
                concat('attachments/', u.Filename) as Path,
                '' as Type,
                0 as Size,
                MembershipUser_Id InsertUserID,
                u.DateCreated as DateInserted
            from UploadedFile u, MediaId m
            where u.Post_Id <> '' and m.Id = u.Id
        ", $attachment_Map);
        */
        $ex->endExport();
    }
}

// Closing PHP tag required. (make.php)
?>
