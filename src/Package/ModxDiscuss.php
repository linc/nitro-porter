<?php
/**
 * MODX Discuss exporter tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Robin Jurinka
 */

namespace NitroPorter\Package;

use NitroPorter\ExportController;
use NitroPorter\ExportModel;

class ModxDiscuss extends ExportController
{

    const SUPPORTED = [
        'name' => 'MODX Discuss Extension',
        'prefix' => 'modx_discuss_',
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

    /**
     * You can use this to require certain tables and columns be present.
     *
     * This can be useful for verifying data integrity. Don't specify more columns
     * than your porter actually requires to avoid forwards-compatibility issues.
     *
     * @var array Required tables => columns
     */
    protected $sourceTables = array(
        'categories' => array(), // This just requires the 'forum' table without caring about columns.
        'boards' => array(),
        'posts' => array(),
        'threads' => array(),
        'users' => array('user', 'username', 'email', 'createdon', 'gender', 'birthdate', 'location', 'confirmed', 'last_login', 'last_active', 'title', 'avatar', 'show_email'), // Require specific cols on 'users'
    );

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see   $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex)
    {
        // Get the characterset for the comments.
        // Usually the comments table is the best target for this.
        $characterSet = $ex->getCharacterSet('posts');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Reiterate the platform name here to be included in the porter file header.
        $ex->beginExport('', 'MODX Discuss Extension', array('HashMethod' => 'Vanilla'));

        // User.
        $ex->exportTable(
            'User', "
            select
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

        /**
         *  No Roles in discuss, wiping the role table doesn't make sense!
         */
        // Role.
        // The Vanilla roles table will be wiped by any import. If your current platform doesn't have roles,
        // you can hard code new ones into the select statement. See Vanilla's defaults for a good example.
        /*
        $role_Map = array(
            'Group_ID' => 'RoleID',
            'Name' => 'Name', // We let these arrays end with a comma to prevent typos later as we add.
        );
        $ex->exportTable('Role', "
         select *
         from :_tblGroup", $role_Map);
        */

        // User Role.
        // Really simple matchup.
        // Note that setting Admin=1 on the User table trumps all roles & permissions with "owner" privileges.
        // Whatever account you select during the import will get the Admin=1 flag to prevent permissions issues.
        $ex->exportTable(
            'UserRole', "
            select
                u.user as UserID,
                u.roleID as RoleID,
                '32' as roleID
            from :_moderators u"
        );

        // Permission.
        // Feel free to add a permission export if this is a major platform or it will see reuse.
        // For small or custom jobs, it's usually not worth it. Just fix them afterward.

        // UserMeta.
        // This is an example of pulling Signatures into Vanilla's UserMeta table.
        // This is often a good place for any extraneous data on the User table too.
        // The Profile Extender addon uses the namespace "Profile.[FieldName]"
        // You can add the appropriately-named fields after the migration and profiles will auto-populate with the migrated data.
        $ex->exportTable(
            'UserMeta', "
            select
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
            where name_first <> ''
        "
        );

        // Category.
        // Be careful to not import hundreds of categories. Try translating huge schemas to Tags instead.
        // Numeric category slugs aren't allowed in Vanilla, so be careful to sidestep those.
        // Don't worry about rebuilding the TreeLeft & TreeRight properties. Vanilla can fix this afterward
        // if you just get the Sort and ParentIDs correct.
        $ex->exportTable(
            'Category', "
            select
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
            from :_categories
        "
        );

        // Discussion.
        // A frequent issue is for the OPs content to be on the comment/post table, so you may need to join it.
        $discussion_Map = array(
            'title2' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
        );
        // It's easier to convert between Unix time and MySQL datestamps during the db query.
        $ex->exportTable(
            'Discussion', "
            select
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

        // Comment.
        // This is where big migrations are going to get bogged down.
        // Be sure you have indexes created for any columns you are joining on.
        $ex->exportTable(
            'Comment', '
            select
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

        // UserDiscussion.
        // This is the table for assigning bookmarks/subscribed threads.

        // Media.
        // Attachment data goes here. Vanilla attachments are files under the /uploads folder.
        // This is usually the trickiest step because you need to translate file paths.
        // If you need to export blobs from the database, see the vBulletin porter.

        // Conversations.
        // Private messages often involve the most data manipulation.
        // If you need a large number of complex SQL statements, consider making it a separate method
        // to keep the main process easy to understand. Pass $ex as a parameter if you do.

        $ex->endExport();
    }
}

