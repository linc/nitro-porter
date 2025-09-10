<?php

/**
 * MVC exporter tool.
 *
 * @author  Olivier Lamy-Canuel
 */

namespace Porter\Source;

use Porter\Source;
use Porter\Migration;

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
     * @param Migration $port
     */
    public function run(Migration $port): void
    {
        $this->createPrimaryKeys($port);
        $this->createIndexesIfNotExists($port);

        $this->users($port);
        $this->userMeta($port);
        $this->roles($port);
        $this->badges($port);

        $this->categories($port);
        $this->discussions($port);
        $this->comments($port);
        $this->tags($port);
        $this->attachments($port);
    }

    /**
     * Create indexes on current tables to accelerate the export process. Initial ids are varchar, which can make the
     * queries hang when joining or using some columns in conditions. Ignore the creation if the index already exist.
     */
    private function createIndexesIfNotExists(Migration $port): void
    {
        if (!$port->indexExists('mvc_users_id', ':_MembershipUser')) {
            $port->query("create INDEX mvc_users_id on :_MembershipUser(Id);");
        }
        if (!$port->indexExists('mvc_role_id', ':_MembershipRole')) {
            $port->query("create INDEX mvc_role_id on `:_MembershipRole` (Id);");
        }
        if (!$port->indexExists('mvc_badge_id', ':_Badge')) {
            $port->query("create INDEX mvc_badge_id on `:_Badge` (Id);");
        }
        if (!$port->indexExists('mvc_category_id', ':_Category')) {
            $port->query("create INDEX mvc_category_id on `:_Category` (Id);");
        }
        if (!$port->indexExists('mvc_tag_id', ':_TopicTag')) {
            $port->query("create INDEX mvc_tag_id on `:_TopicTag` (Id);");
        }
        if (!$port->indexExists('mvc_file_id', ':_UploadedFile')) {
            $port->query("create INDEX mvc_file_id on `:_UploadedFile` (Id);");
        }

        // Topic
        if (!$port->indexExists('mvc_topic_id', ':_Topic')) {
            $port->query("create INDEX mvc_topic_id on `:_Topic` (Id);");
        }
        if (!$port->indexExists('mvc_topic_id', ':_Topic')) {
            $port->query("create INDEX mvc_topic_membershipuser_id on `:_Topic` (MembershipUser_Id);");
        }
        if (!$port->indexExists('mvc_topic_id', ':_Topic')) {
            $port->query("create INDEX mvc_topic_category_id on `:_Topic` (Category_Id);");
        }

        // Post
        if (!$port->indexExists('mvc_post_id', ':_Post')) {
            $port->query("create INDEX mvc_post_id on `:_Post` (Id);");
        }
        if (!$port->indexExists('mvc_post_id', ':_Post')) {
            $port->query("create INDEX mvc_post_topic_id on `:_Post` (Topic_Id);");
        }
        if (!$port->indexExists('mvc_post_id', ':_Post')) {
            $port->query("create INDEX mvc_post_membershipuser_id on `:_Post` (MembershipUser_Id);");
        }
    }

    /**
     * For each table in the database, check if the primary key exists and create it if it doesn't.
     */
    private function createPrimaryKeys(Migration $port): void
    {
        $this->addMembershipUserPrimaryKeyIfNotExists($port);
        $this->addRolePrimaryKeyIfNotExists($port);
        $this->addBadgePrimaryKeyIfNotExists($port);
        $this->addCategoryPrimaryKeyIfNotExists($port);
        $this->addTopicPrimaryKeyIfNotExists($port);
        $this->addPostPrimaryKeyIfNotExists($port);
        $this->addTopicTagPrimaryKeyIfNotExists($port);
        $this->addUploadFilePrimaryKeyIfNotExists($port);
    }

    /**
     * Add the UserID column to the `MembershipUser` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addMembershipUserPrimaryKeyIfNotExists(Migration $port): void
    {
        if (!$port->columnExists('MembershipUser', 'UserID')) {
            $port->query("alter table :_MembershipUser add column UserID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the RoleID column to the `MembershipRole` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addRolePrimaryKeyIfNotExists(Migration $port): void
    {
        if (!$port->columnExists('MembershipRole', 'RoleID')) {
            $port->query("alter table :_MembershipRole add column RoleID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the BadgeID column to the `Badge` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addBadgePrimaryKeyIfNotExists(Migration $port): void
    {
        if (!$port->columnExists('Badge', 'BadgeID')) {
            $port->query("alter table :_Badge add column BadgeID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the CategoryID column to the `Category` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addCategoryPrimaryKeyIfNotExists(Migration $port): void
    {
        if (!$port->columnExists('Category', 'CategoryID')) {
            $port->query("alter table :_Category add column CategoryID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the DiscussionID column to the Topic` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addTopicPrimaryKeyIfNotExists(Migration $port): void
    {
        if (!$port->columnExists('Topic', 'TopicID')) {
            $port->query("alter table :_Topic add column TopicID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the CommentID column to the 'Post` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addPostPrimaryKeyIfNotExists(Migration $port): void
    {
        if (!$port->columnExists('Post', 'PostID')) {
            $port->query("alter table :_Post add column PostID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the TagID column to the 'TopicTag` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addTopicTagPrimaryKeyIfNotExists(Migration $port): void
    {
        if (!$port->columnExists('TopicTag', 'TagID')) {
            $port->query("alter table :_TopicTag add column TagID int(11) primary key auto_increment");
        }
    }

    /**
     * Add the MediaID column to the 'UploadedFile` table if it doesn't exist. Setting this column as the primary key
     * will generate a new unique id for each records.
     */
    private function addUploadFilePrimaryKeyIfNotExists(Migration $port): void
    {
        if (!$port->columnExists('UploadedFile', 'MediaID')) {
            $port->query("alter table :_UploadedFile add column MediaID int(11) primary key auto_increment");
        }
    }

    /**
     * @param Migration $port
     */
    protected function users(Migration $port): void
    {
        $port->export(
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
     * @param Migration $port
     */
    protected function userMeta(Migration $port): void
    {
        $port->export(
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
     * @param Migration $port
     */
    protected function roles(Migration $port): void
    {
        $port->export(
            'Role',
            "select RoleID, RoleName as Name from :_MembershipRole"
        );

        // User Role.
        $port->export(
            'UserRole',
            "select
                    u.UserID as UserID,
                    r.RoleID as RoleID
                from :_MembershipUsersInRoles m,  :_MembershipRole r, :_MembershipUser u
                where r.RoleID = m.RoleIdentifier and u.UserID = m.UserIdentifier"
        );
    }

    /**
     * @param Migration $port
     */
    protected function badges(Migration $port): void
    {
        $port->export(
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

        $port->export(
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
     * @param Migration $port
     */
    protected function categories(Migration $port): void
    {
        $port->export(
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
     * @param Migration $port
     */
    protected function discussions(Migration $port): void
    {
        $port->export(
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
     * @param Migration $port
     */
    protected function comments(Migration $port): void
    {
        $port->export(
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
     * @param Migration $port
     */
    protected function tags(Migration $port): void
    {
        $port->export(
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
     * @param Migration $port
     */
    protected function attachments(Migration $port): void
    {
        // Use of placeholder for Type and Size due to lack of data in db.
        // Will require external script to get the info.
        $port->export(
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
