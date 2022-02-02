<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author YOUR NAME
 */

namespace Porter\Package;

use Porter\Package;
use Porter\ExportModel;

class Example extends Package
{
    public const SUPPORTED = [
        'name' => '_Example',
        'prefix' => '',
        'charset_table' => 'comments',  // Usually put the comments table name here. Used to derive charset.
        'options' => [
        ],
        'features' => [  // Set features you support to 1 or a string (for support notes).
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
     * You can use this to require certain tables and columns be present.
     *
     * This can be useful for verifying data integrity. Don't specify more columns
     * than your porter actually requires to avoid forwards-compatibility issues.
     *
     * @var array Required tables => columns
     */
    public $sourceTables = [
        'forums' => [], // This just requires the 'forum' table without caring about columns.
        'posts' => [],
        'topics' => [],
        'users' => ['ID', 'user_login', 'user_pass', 'user_email'], // Require specific cols on 'users'
    ];

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function run(ExportModel $ex)
    {
        // It's usually a good idea to do the porting in the approximate order laid out here.
        $this->users($ex); // Always pass $ex to these methods.
        $this->roles($ex);

        // Permission.
        // Feel free to add a permission export if this is a major platform or it will see reuse.
        // For small or custom jobs, it's usually not worth it. Just fix them afterward.

        $this->userMeta($ex);
        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);

        // UserDiscussion.
        // This is the table for assigning bookmarks/subscribed threads.

        // Media.
        // Attachment data goes here. Vanilla attachments are files under the /uploads folder.
        // This is usually the trickiest step because you need to translate file paths.
        // If you need to export blobs from the database, see the vBulletin porter.

        // Conversations.
        // Private messages often involve the most data manipulation.
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        // Map as much as possible using the $x_Map array for clarity.
        // Key is always the source column name.
        // Value is either the destination column or an array of meta data, usually Column & Filter.
        // If it's a meta array, 'Column' is the destination column name and 'Filter' is a method name to run it thru.
        // Here, 'HTMLDecoder' is a method in ExportModel. Check there for available filters.
        // Assume no filter is needed and only use one if you encounter issues.
        $user_Map = [
            'Author_ID' => 'UserID',
            'Username' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
        ];
        // This is the query that the x_Map array above will be mapped against.
        // Therefore, our select statement must cover all the "source" columns.
        // It's frequently necessary to add joins, where clauses, and more to get the data we want.
        // The :_ before the table name is the placeholder for the prefix designated. It gets swapped on the fly.
        $ex->exportTable(
            'User',
            "select u.* from :_User u",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        // Role.
        // The Vanilla roles table will be wiped by any import. If your current platform doesn't have roles,
        // you can hard code new ones into the select statement. See Vanilla's defaults for a good example.
        $role_Map = array(
            'Group_ID' => 'RoleID',
            'Name' => 'Name', // We let these arrays end with a comma to prevent typos later as we add.
        );
        $ex->exportTable(
            'Role',
            "select * from :_tblGroup",
            $role_Map
        );

        // User Role.
        // Really simple matchup.
        // Note that setting Admin=1 on the User table trumps all roles & permissions with "owner" privileges.
        // Whatever account you select during the import will get the Admin=1 flag to prevent permissions issues.
        $userRole_Map = [
            'Author_ID' => 'UserID',
            'Group_ID' => 'RoleID',
        ];
        $ex->exportTable(
            'UserRole',
            "select u.* from :_tblAuthor u",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function userMeta(ExportModel $ex): void
    {
        // This is an example of pulling Signatures into Vanilla's UserMeta table.
        // This is often a good place for any extraneous data on the User table too.
        // The Profile Extender addon uses the namespace "Profile.[FieldName]"
        // You can add the appropriately-named fields after the migration.
        // Profiles will auto-populate with the migrated data.
        $ex->exportTable(
            'UserMeta',
            "select
                Author_ID as UserID,
                'Plugin.Signatures.Sig' as `Name`,
                Signature as `Value`
             from :_tblAuthor
             where Signature <> ''"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        // Be careful to not import hundreds of categories. Try translating huge schemas to Tags instead.
        // Numeric category slugs aren't allowed in Vanilla, so be careful to sidestep those.
        // Don't worry about rebuilding the TreeLeft & TreeRight properties. Vanilla can fix this afterward
        // if you just get the Sort and ParentIDs correct.
        $category_Map = [
            'Forum_ID' => 'CategoryID',
            'Forum_name' => 'Name',
        ];
        $ex->exportTable(
            'Category',
            "select * from :_tblCategory c",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        // A frequent issue is for the OPs content to be on the comment/post table, so you may need to join it.
        $discussion_Map = array(
            'Topic_ID' => 'DiscussionID',
            'Forum_ID' => 'CategoryID',
            'Author_ID' => 'InsertUserID',
            'Subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
        );
        // It's easier to convert between Unix time and MySQL datestamps during the db query.
        $ex->exportTable(
            'Discussion',
            "select *, FROM_UNIXTIME(Message_date) as Message_date
                 from :_tblTopic t
                 join :_tblThread th
                    on t.Start_Thread_ID = th.Thread_ID",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        // This is where big migrations are going to get bogged down.
        // Be sure you have indexes created for any columns you are joining on.
        $comment_Map = [
            'Thread_ID' => 'CommentID',
            'Topic_ID' => 'DiscussionID',
            'Author_ID' => 'InsertUserID',
            'IP_addr' => 'InsertIPAddress',
            'Message' => ['Column' => 'Body'],
            'Format' => 'Format',
            'Message_date' => ['Column' => 'DateInserted']
        ];
        $ex->exportTable(
            'Comment',
            "select th.* from :_tblThread th",
            $comment_Map
        );
    }
}
