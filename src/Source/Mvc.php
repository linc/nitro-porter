<?php

/**
 * MVC exporter tool.
 *
 * @author  Olivier Lamy-Canuel
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class Mvc extends Source
{
    public const SUPPORTED = [
        'name' => 'MVC',
        'prefix' => '',
        'charset_table' => 'Post',
        'options' => [],
        'features' => [
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
        ]
    ];

    /**
     * You can use this to require certain tables and columns be present.
     *
     * @var array Required tables => columns
     */
    public array $sourceTables = array(
        'MembershipUser' => array(),
        'Catagory' => array(),
        'Post' => array(),
        'Topic' => array(),
    );

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see   $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function run($ex)
    {
        $this->createPrimaryKeys($ex);
        $this->createIndexesIfNotExists($ex);

        $this->users($ex);
        $this->userMeta($ex);
        $this->roles($ex);
        $this->badges($ex);

        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);
        $this->tags($ex);
        $this->attachments($ex);
    }

    /**
     * Create indexes on current tables to accelerate the export process. Initial ids are varchar, which can make the
     * queries hang when joining or using some columns in conditions. Ignore the creation if the index already exist.
     */
    private function createIndexesIfNotExists($ex)
    {
        if (!$ex->indexExists('mvc_users_id', ':_MembershipUser')) {
            $ex->query("create INDEX mvc_users_id on :_MembershipUser(Id);");
        }
        if (!$ex->indexExists('mvc_role_id', ':_MembershipRole')) {
            $ex->query("create INDEX mvc_role_id on `:_MembershipRole` (Id);");
        }
        if (!$ex->indexExists('mvc_badge_id', ':_Badge')) {
            $ex->query("create INDEX mvc_badge_id on `:_Badge` (Id);");
        }
        if (!$ex->indexExists('mvc_category_id', ':_Category')) {
            $ex->query("create INDEX mvc_category_id on `:_Category` (Id);");
        }
        if (!$ex->indexExists('mvc_tag_id', ':_TopicTag')) {
            $ex->query("create INDEX mvc_tag_id on `:_TopicTag` (Id);");
        }
        if (!$ex->indexExists('mvc_file_id', ':_UploadedFile')) {
            $ex->query("create INDEX mvc_file_id on `:_UploadedFile` (Id);");
        }

        // Topic
        if (!$ex->indexExists('mvc_topic_id', ':_Topic')) {
            $ex->query("create INDEX mvc_topic_id on `:_Topic` (Id);");
        }
        if (!$ex->indexExists('mvc_topic_id', ':_Topic')) {
            $ex->query("create INDEX mvc_topic_membershipuser_id on `:_Topic` (MembershipUser_Id);");
        }
        if (!$ex->indexExists('mvc_topic_id', ':_Topic')) {
            $ex->query("create INDEX mvc_topic_category_id on `:_Topic` (Category_Id);");
        }

        // Post
        if (!$ex->indexExists('mvc_post_id', ':_Post')) {
            $ex->query("create INDEX mvc_post_id on `:_Post` (Id);");
        }
        if (!$ex->indexExists('mvc_post_id', ':_Post')) {
            $ex->query("create INDEX mvc_post_topic_id on `:_Post` (Topic_Id);");
        }
        if (!$ex->indexExists('mvc_post_id', ':_Post')) {
            $ex->query("create INDEX mvc_post_membershipuser_id on `:_Post` (MembershipUser_Id);");
        }
    }

    /**
     * For each table in the database, check if the primary key exists and create it if it doesn't.
     */
    private function createPrimaryKeys($ex)
    {
        $this->addMembershipUserPrimaryKeyIfNotExists($ex);
        $this->addRolePrimaryKeyIfNotExists($ex);
        $this->addBadgePrimaryKeyIfNotExists($ex);
        $this->addCategoryPrimaryKeyIfNotExists($ex);
        $this->addTopicPrimaryKeyIfNotExists($ex);
        $this->addPostPrimaryKeyIfNotExists($ex);
        $this->addTopicTagPrimaryKeyIfNotExists($ex);
        $this->addUploadFilePrimaryKeyIfNotExists($ex);
    }

    /**
     * Add the UserID column to the `MembershipUser` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addMembershipUserPrimaryKeyIfNotExists($ex)
    {
        if (!$ex->columnExists('MembershipUser', 'UserID')) {
            $ex->query("alter table :_MembershipUser add column UserID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the RoleID column to the `MembershipRole` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addRolePrimaryKeyIfNotExists($ex)
    {
        if (!$ex->columnExists('MembershipRole', 'RoleID')) {
            $ex->query("alter table :_MembershipRole add column RoleID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the BadgeID column to the `Badge` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addBadgePrimaryKeyIfNotExists($ex)
    {
        if (!$ex->columnExists('Badge', 'BadgeID')) {
            $ex->query("alter table :_Badge add column BadgeID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the CategoryID column to the `Category` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addCategoryPrimaryKeyIfNotExists($ex)
    {
        if (!$ex->columnExists('Category', 'CategoryID')) {
            $ex->query("alter table :_Category add column CategoryID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the DiscussionID column to the Topic` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addTopicPrimaryKeyIfNotExists($ex)
    {
        if (!$ex->columnExists('Topic', 'TopicID')) {
            $ex->query("alter table :_Topic add column TopicID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the CommentID column to the 'Post` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addPostPrimaryKeyIfNotExists($ex)
    {
        if (!$ex->columnExists('Post', 'PostID')) {
            $ex->query("alter table :_Post add column PostID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the TagID column to the 'TopicTag` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addTopicTagPrimaryKeyIfNotExists($ex)
    {
        if (!$ex->columnExists('TopicTag', 'TagID')) {
            $ex->query("alter table :_TopicTag add column TagID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the MediaID column to the 'UploadedFile` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addUploadFilePrimaryKeyIfNotExists($ex)
    {
        if (!$ex->columnExists('UploadedFile', 'MediaID')) {
            $ex->query("alter table :_UploadedFile add column MediaID int(11) primary key auto_increment");
        }
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $ex->export(
            'User',
            "select
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
                from :_MembershipUser m"
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
                    UserID,
                    'Website' as `Name`,
                    Website as `Value`
                from :_MembershipUser m
                where m.Website <> ''
                union
                select
                    UserID,
                    'Signatures.Sig',
                    Signature
                from :_MembershipUser m
                where m.Signature <> ''"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $ex->export(
            'Role',
            "select RoleID, RoleName as Name from :_MembershipRole"
        );

        // User Role.
        $ex->export(
            'UserRole',
            "select
                    u.UserID as UserID,
                    r.RoleID as RoleID
                from :_MembershipUsersInRoles m,  :_MembershipRole r, :_MembershipUser u
                where r.RoleID = m.RoleIdentifier and u.UserID = m.UserIdentifier"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function badges(ExportModel $ex): void
    {
        $ex->export(
            'Badge',
            "select
                    BadgeID,
                    Type as Type,
                    DisplayName as Name,
                    Description as Body,
                    Image as Photo,
                    AwardsPoints as Points
                from :_Badge"
        );

        $ex->export(
            'UserBadge',
            "select
                    u.UserID,
                    b.BadgeID,
                    '' as Status,
                    now() as DateInserted
                from :_MembershipUser_Badge m, :_MembershipUser u, :_Badge b
                where u.UserID = m.MembershipUser_Id and b.BadgeID = m.Badge_Id"
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
                where m.Category_Id = ''"
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
                    m.TopicID as DiscussionID,
                    c.CategoryID as CategoryID,
                    u.UserID as InsertUserID,
                    m.CreateDate as DateInserted,
                    m.Name as Name,
                    m.Views as CountViews,
                    'Html' as Format
                from :_Topic m
                left join :_MembershipUser u on u.Id = m.MembershipUser_Id
                left join :_Category c on c.Id = m.Category_Id"
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
                    m.PostID as CommentID,
                    d.TopicID as DiscussionID,
                    u.UserID as InsertUserID,
                    m.PostContent as Body,
                    m.DateCreated as DateInserted,
                    m.DateEdited as DateUpdated,
                    'Html' as Format
                from :_Post m
                left join :_Topic d on d.Id = m.Topic_Id
                left join :_MembershipUser u on u.Id = m.MembershipUser_Id"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function tags(ExportModel $ex): void
    {
        $ex->export(
            'Tag',
            "select
                    TagID,
                    Tag as Name,
                    Tag as FullName,
                    now() as DateInserted
                from TopicTag"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function attachments(ExportModel $ex): void
    {
        // Use of placeholder for Type and Size due to lack of data in db.
        // Will require external script to get the info.
        $ex->export(
            'Attachment',
            "select
                    MediaID,
                    Filename as Name,
                    concat('attachments/', u.Filename) as Path,
                    '' as Type,
                    0 as Size,
                    MembershipUser_Id InsertUserID,
                    u.DateCreated as DateInserted
                from :_UploadedFile u
                where u.Post_Id <> '' and m.Id = u.Id"
        );
    }
}
