<?php

/**
 * MODX Discuss exporter tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Robin Jurinka
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class ModxDiscuss extends Source
{
    public const SUPPORTED = [
        'name' => 'MODX Discuss Extension',
        'prefix' => 'modx_discuss_',
        'charset_table' => 'posts',
        'hashmethod' => 'Vanilla',
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
     * You can use this to require certain tables and columns be present.
     *
     * This can be useful for verifying data integrity. Don't specify more columns
     * than your porter actually requires to avoid forwards-compatibility issues.
     *
     * @var array Required tables => columns
     */
    public $sourceTables = array(
        'categories' => array(), // This just requires the 'forum' table without caring about columns.
        'boards' => array(),
        'posts' => array(),
        'threads' => array(),
        'users' => array('user', 'username', 'email', 'createdon', 'gender',
            'birthdate', 'location', 'confirmed', 'last_login', 'last_active',
            'title', 'avatar', 'show_email'), // Require specific cols on 'users'
    );

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see   $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function run($ex)
    {
        $this->users($ex);
        $this->roles($ex);
        $this->userMeta($ex);
        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $ex->exportTable(
            'User',
            "select
                    u.user as UserID,
                    u.username as Name,
                    u.password as Password,
                    u.email as Email,
                    u.ip as LastIPAddress,
                    u.createdon as DateInserted,
                    u.gender2 as Gender, // 0 => 'u'
                    u.birthdate as DateOfBirth,
                    u.location as Location,
                    u.confirmed as Confirmed,
                    u.last_active as DateLastActive,
                    u.title as Title,
                    u.avatar as Photo,
                    u.show_email as ShowEmail,
                    case u.gender when 0 then 'u' else gender end as gender2
                from :_users u"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        // Roles do not exist in Discuss. Really simple matchup.
        // Note that setting Admin=1 on the User table trumps all roles & permissions with "owner" privileges.
        // Whatever account you select during the import will get the Admin=1 flag to prevent permissions issues.
        $ex->exportTable(
            'UserRole',
            "select
                    u.user as UserID,
                    u.roleID as RoleID,
                    '32' as roleID
                from :_moderators u"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function userMeta(ExportModel $ex): void
    {
        $ex->exportTable(
            'UserMeta',
            "select
                    user as UserID,
                    'Plugin.Signatures.Sig' as `Name`,
                    Signature as `Value`
                from :_users
                where Signature <> ''
                union
                select
                    user as UserID,
                    'Profile.Website' as `Name`,
                    website as `Value`
                from :_users
                where website <> ''
                union
                select
                    user as UserID,
                    'Profile.LastName' as `Name`,
                    name_last as `Value`
                from :_users
                where name_last <> ''
                union
                select
                    user as UserID,
                    'Profile.FirstName' as `Name`,
                    name_first as `Value`
                from :_users
                where name_first <> ''"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $ex->exportTable(
            'Category',
            "select
                    id as CategoryID,
                    case parent when 0 then '-1' else parent end as ParentCategoryID,
                    name as `Name`,
                    description as `Description`,
                    'Heading' as `DisplayAs`,
                rank as `Sort`
                from :_boards
                union
                select
                    (select max(id) from :_boards) + id as CategoryID,
                    '-1' as ParentCategoryID,
                    name as `Name`,
                    description as `Description`,
                    'Heading' as `DisplayAs`,
                    rank as `Sort`
                from :_categories"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
            'title2' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
        );
        // It's easier to convert between Unix time and MySQL datestamps during the db query.
        $ex->exportTable(
            'Discussion',
            "select
                    t.id as `DiscussionID`,
                    t.board as `CategoryID`,
                    t.title as `title2`,
                    t.replies as `CountComments`,
                    t.views as `CountViews`,
                    t.locked as `Closed`,
                    t.sticky as `Announce`,
                    p.message as `Body`,
                    'BBCode' as `Format`,
                    p.author as `InsertUserID`,
                    p.createdon as `DateInserted`,
                    p.editedon as `DateUpdated`,
                    p.ip as `InsertIPAddress`,
                    case p.editedby when 0 then null else p.editedby end as `UpdateUserID`
                from :_threads t
                join :_posts p on t.id = p.thread",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $ex->exportTable(
            'Comment',
            'select
                    p.id as CommentID,
                    p.thread as DiscussionID,
                    p.message as Body,
                    p.BBCode as Format,
                    p.author as InsertUserID,
                    p.createdon as DateInserted,
                    p.editedby2 as UpdateUserID,
                    p.editedon as DateUpdated,
                    p.ip as InsertIPAddress,
                    case p.editedby when 0 then null else p.editedby end as editedby2
                from :_posts p
                where p.parent <> 0'
        );
    }
}
