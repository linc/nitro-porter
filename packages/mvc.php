<?php
/**
 * MVC exporter tool.
 *
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license GNU GPL2
 * @package VanillaPorter
 * @see functions.commandline.php for command line usage.
 */

$supported['mvc'] = array('name' => 'mvc', 'prefix' => '');
$supported['mvc']['features'] = array(
    'Users' => 1,
    'Passwords' => 0,
    'Categories' => 1,
    'Discussions' => 1,
    'Comments' => 1,
    'Polls' => 0,
    'Roles' => 1,
    'Avatars' => 1,
    'PrivateMessages' => 0,
    'Signatures' => 1,
    'Attachments' => 0,
    'Bookmarks' => 0,
    'Permissions' => 0,
    'Badges' => 1,
    'UserNotes' => 0,
    'Ranks' => 0,
    'Groups' => 0,
    'Tags' => 1,
    'UserTags' => 0,
    'Reactions' => 0,
    'Articles' => 0,
);

class MVC extends ExportController {
    /**
     * You can use this to require certain tables and columns be present.
     *
     * @var array Required tables => columns
     */
    protected $sourceTables = array(
        'MembershipUser' => array(),
        'Catagory' => array(),
        'Post' => array(),
        'Topic' => array(),
    );

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex) {
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

        $this->createPrimaryKeys();
        $this->createIndexesIfNotExists();

        // Users.
        $ex->exportTable('User', "
            select
                UserID,
                UserName as Name,
                'Reset' as HashMethod,
                Email as Email,
                Avatar as Photo,
                CreateDate as DateInserted,
                LastLoginDate as DateLastVisit,
                LastActivityDate as DateLastActive,
                IsBanned as Banned,
                Location as Location
            from
                :_MembershipUser m
         ");

        // UserMeta.
        $ex->exportTable('UserMeta', "
            select
                UserID,
                'Website' as `Name`,
                Website as `Value`
            from
                :_MembershipUser m
            where
                m.Website <> ''

            union

            select
                UserID,
                'Signatures.Sig',
                Signature
            from
                :_MembershipUser m
            where
                m.Signature <> ''
        ");

        // Role.
        $ex->exportTable('Role', "
            select
                RoleID,
                RoleName as Name
            from
                :_MembershipRole
         ");

        // User Role.
        $ex->exportTable('UserRole', "
            select
                u.UserID as UserID,
                r.RoleID as RoleID
            from :_MembershipUsersInRoles m,  :_MembershipRole r, :_MembershipUser u
            where r.RoleID = m.RoleIdentifier and u.UserID = m.UserIdentifier
        ");

        //Badge.
        $ex->exportTable('Badge', "
            select
                BadgeID,
                Type as Type,
                DisplayName as Name,
                Description as Body,
                Image as Photo,
                AwardsPoints as Points
            from
                :_Badge
        ");

        $ex->exportTable('UserBadge', "
            select
                u.UserID,
                b.BadgeID,
                '' as Status,
                now() as DateInserted
            from :_MembershipUser_Badge m, :_MembershipUser u, :_Badge b
            where u.UserID = m.MembershipUser_Id and b.BadgeID = m.Badge_Id
        ");

        // Category.
        $ex->exportTable('Category', "
            select
                m.CategoryID,
                p.CategoryID as ParentCategoryID,
                m.Name as Name,
                m.Description as Description,
                m.DateCreated as DateInserted,
                null as Sort
            from Category m, Category p
            where m.Category_Id <> '' and p.CategoryID = m.Category_Id

            union

            select
                m.CategoryID,
                '-1' as ParentCategoryID,
                m.Name as Name,
                m.Description as Description,
                m.DateCreated as DateInserted,
                null as Sort
            from Category m
            where m.Category_Id = ''
        ");

        // Discussion.
        $ex->exportTable('Discussion', "
            select
                m.TopicID as DiscussionID,
                c.CategoryID as CategoryID,
                u.UserID as InsertUserID,
                m.CreateDate as DateInserted,
                m.Name as Name,
                m.Views as CountViews,
                'Html' as Format
            from
                :_Topic m
            left join
                :_MembershipUser u on u.Id = m.MembershipUser_Id
            left join
                :_Category c on c.Id = m.Category_Id
            ");

        // Comment.
        $ex->exportTable('Comment', "
            select
                m.PostID as CommentID,
                d.TopicID as DiscussionID,
                u.UserID as InsertUserID,
                m.PostContent as Body,
                m.DateCreated as DateInserted,
                m.DateEdited as DateUpdated,
                'Html' as Format
            from
                :_Post m
            left join
                :_Topic d on d.Id = m.Topic_Id
            left join
                :_MembershipUser u on u.Id = m.MembershipUser_Id
         ");

        // Tag
        $ex->exportTable('Tag', "
            select
                TagID,
                Tag as Name,
                Tag as FullName,
                now() as DateInserted
            from TopicTag
         ");

        //Attachment WIP
        // Use of placeholder for Type and Size due to lack of data in db. Will require external script to get the info.
        $ex->exportTable('Attachment', "
            select
                MediaID,
                Filename as Name,
                concat('attachments/', u.Filename) as Path,
                '' as Type,
                0 as Size,
                MembershipUser_Id InsertUserID,
                u.DateCreated as DateInserted
            from :_UploadedFile u
            where u.Post_Id <> '' and m.Id = u.Id
        ");

        $ex->endExport();
    }

    /**
     * Create indexes on current tables to accelerate the export process. Initial ids are varchar, which can make the
     * queries hang when joining or using some columns in conditions. Ignore the creation if the index already exist.
     */
    private function createIndexesIfNotExists() {
        if (!$this->ex->indexExists('mvc_users_id', ':_MembershipUser')) {
            $this->ex->query("create INDEX mvc_users_id on :_MembershipUser(Id);");
        }
        if (!$this->ex->indexExists('mvc_role_id', ':_MembershipRole')) {
            $this->ex->query("create INDEX mvc_role_id on `:_MembershipRole` (Id);");
        }
        if (!$this->ex->indexExists('mvc_badge_id', ':_Badge')) {
            $this->ex->query("create INDEX mvc_badge_id on `:_Badge` (Id);");
        }
        if (!$this->ex->indexExists('mvc_category_id', ':_Category')) {
            $this->ex->query("create INDEX mvc_category_id on `:_Category` (Id);");
        }
        if (!$this->ex->indexExists('mvc_tag_id', ':_TopicTag')) {
            $this->ex->query("create INDEX mvc_tag_id on `:_TopicTag` (Id);");
        }
        if (!$this->ex->indexExists('mvc_file_id', ':_UploadedFile')) {
            $this->ex->query("create INDEX mvc_file_id on `:_UploadedFile` (Id);");
        }

        // Topic
        if (!$this->ex->indexExists('mvc_topic_id', ':_Topic')) {
            $this->ex->query("create INDEX mvc_topic_id on `:_Topic` (Id);");
        }
        if (!$this->ex->indexExists('mvc_topic_id', ':_Topic')) {
            $this->ex->query("create INDEX mvc_topic_membershipuser_id on `:_Topic` (MembershipUser_Id);");
        }
        if (!$this->ex->indexExists('mvc_topic_id', ':_Topic')) {
            $this->ex->query("create INDEX mvc_topic_category_id on `:_Topic` (Category_Id);");
        }

        // Post
        if (!$this->ex->indexExists('mvc_post_id', ':_Post')) {
            $this->ex->query("create INDEX mvc_post_id on `:_Post` (Id);");
        }
        if (!$this->ex->indexExists('mvc_post_id', ':_Post')) {
            $this->ex->query("create INDEX mvc_post_topic_id on `:_Post` (Topic_Id);");
        }
        if (!$this->ex->indexExists('mvc_post_id', ':_Post')) {
            $this->ex->query("create INDEX mvc_post_membershipuser_id on `:_Post` (MembershipUser_Id);");
        }
    }

    /**
     * For each table in the database, check if the primary key exists and create it if it doesn't.
     */
    private function createPrimaryKeys() {
        $this->addMembershipUserPrimaryKeyIfNotExists();
        $this->addRolePrimaryKeyIfNotExists();
        $this->addBadgePrimaryKeyIfNotExists();
        $this->addCategoryPrimaryKeyIfNotExists();
        $this->addTopicPrimaryKeyIfNotExists();
        $this->addPostPrimaryKeyIfNotExists();
        $this->addTopicTagPrimaryKeyIfNotExists();
        $this->addUploadFilePrimaryKeyIfNotExists();
    }

    /**
     * Add the UserID column to the `MembershipUser` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addMembershipUserPrimaryKeyIfNotExists() {
        if (!$this->ex->columnExists('MembershipUser', 'UserID')) {
            $this->ex->query("alter table :_MembershipUser add column UserID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the RoleID column to the `MembershipRole` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addRolePrimaryKeyIfNotExists() {
        if (!$this->ex->columnExists('MembershipRole', 'RoleID')) {
            $this->ex->query("alter table :_MembershipRole add column RoleID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the BadgeID column to the `Badge` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addBadgePrimaryKeyIfNotExists() {
        if (!$this->ex->columnExists('Badge', 'BadgeID')) {
            $this->ex->query("alter table :_Badge add column BadgeID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the CategoryID column to the `Category` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addCategoryPrimaryKeyIfNotExists() {
        if (!$this->ex->columnExists('Category', 'CategoryID')) {
            $this->ex->query("alter table :_Category add column CategoryID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the DiscussionID column to the Topic` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addTopicPrimaryKeyIfNotExists() {
        if (!$this->ex->columnExists('Topic', 'TopicID')) {
            $this->ex->query("alter table :_Topic add column TopicID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the CommentID column to the 'Post` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addPostPrimaryKeyIfNotExists() {
        if (!$this->ex->columnExists('Post', 'PostID')) {
            $this->ex->query("alter table :_Post add column PostID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the TagID column to the 'TopicTag` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addTopicTagPrimaryKeyIfNotExists() {
        if (!$this->ex->columnExists('TopicTag', 'TagID')) {
            $this->ex->query("alter table :_TopicTag add column TagID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the MediaID column to the 'UploadedFile` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addUploadFilePrimaryKeyIfNotExists() {
        if (!$this->ex->columnExists('UploadedFile', 'MediaID')) {
            $this->ex->query("alter table :_UploadedFile add column MediaID int(11) primary key auto_increment");
        }
    }
}

// Closing PHP tag required. (make.php)
?>
