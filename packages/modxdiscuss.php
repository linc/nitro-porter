<?php
/**
 * MODX Discuss exporter tool.
 *
 * @copyright 2017 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

// Add to the $supported array so it appears in the dropdown menu. Uncomment next line.
$supported['modxdiscuss'] = array('name'=> 'MODX Discuss Extension', 'prefix'=>'modx_discuss_');

// Optionally, add the features you are supporting. Set all values to 1 or a string for support notes.
// See functions/feature-functions.php VanillaFeatureSet() for array keys.
$supported['modxdiscuss']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Passwords' => 0,
    'Avatars' => 0,
    'PrivateMessages' => 0,
    'Signatures' => 1,
    'Attachments' => 0,
    'Bookmarks' => 0,
    'Permissions' => 0,
    'UserNotes' => 0,
);

class MODXDiscuss extends ExportController {
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
        'topics' => array(),
        'users' => array('user', 'username', 'email', 'createdon', 'gender', 'birthdate', 'location', 'confirmed', 'last_login', 'last_active', 'title', 'avatar', 'show_email'), // Require specific cols on 'users'
    );

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex) {
        // Get the characterset for the comments.
        // Usually the comments table is the best target for this.
        $characterSet = $ex->getCharacterSet('posts');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Reiterate the platform name here to be included in the porter file header.
        $ex->beginExport('', 'MODX Discuss Extension', array('HashMethod' => 'Vanilla'));

        // It's usually a good idea to do the porting in the approximate order laid out here.

        // User.
        // Map as much as possible using the $x_Map array for clarity.
        // Key is always the source column name.
        // Value is either the destination column or an array of meta data, usually Column & Filter.
        // If it's a meta array, 'Column' is the destination column name and 'Filter' is a method name to run it thru.
        // Here, 'HTMLDecoder' is a method in ExportModel. Check there for available filters.
        // Assume no filter is needed and only use one if you encounter issues.
        $user_Map = array(
            'user' => 'UserID',
            'username' => 'Name',
            'password' => 'Password',
            'email' => 'Email',
            'ip' => 'LastIPAddress',
            'createdon' => 'DateInserted',
            'gender2' => 'Gender', // 0 => 'u'
            'birthdate' => 'DateOfBirth',
            'location' => 'Location',
            'confirmed' => 'Confirmed',
            'last_active' => 'DateLastActive',
            'title' => 'Title',
            'avatar' => 'Photo',
            'show_email' => 'ShowEmail',
        );
        // This is the query that the x_Map array above will be mapped against.
        // Therefore, our select statement must cover all the "source" columns.
        // It's frequently necessary to add joins, where clauses, and more to get the data we want.
        // The :_ before the table name is the placeholder for the prefix designated. It gets swapped on the fly.
        $ex->exportTable('User', "
         select u.*,
         case u.gender when 0 then 'u' else gender end as gender2
         from :_users u
         ", $user_Map);
        
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
        $userRole_Map = array(
            'user' => 'UserID',
            'roleID' => 'RoleID',
        );
        $ex->exportTable('UserRole', "
         select u.*, 32 as roleID 
         from :_tblAuthor u", $userRole_Map);

        // Permission.
        // Feel free to add a permission export if this is a major platform or it will see reuse.
        // For small or custom jobs, it's usually not worth it. Just fix them afterward.

        // UserMeta.
        // This is an example of pulling Signatures into Vanilla's UserMeta table.
        // This is often a good place for any extraneous data on the User table too.
        // The Profile Extender addon uses the namespace "Profile.[FieldName]"
        // You can add the appropriately-named fields after the migration and profiles will auto-populate with the migrated data.
        $ex->exportTable('UserMeta', "
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
        ");

        // Category.
        // Be careful to not import hundreds of categories. Try translating huge schemas to Tags instead.
        // Numeric category slugs aren't allowed in Vanilla, so be careful to sidestep those.
        // Don't worry about rebuilding the TreeLeft & TreeRight properties. Vanilla can fix this afterward
        // if you just get the Sort and ParentIDs correct.
        $ex->exportTable('Category', "
         select
            id as CategoryID,
            name as `Name`,
            description as `Description`,
            'Heading' as `DisplayAs`,
            rank as `Sort`
         from :_categories
         
         union
         
         select
            category as ParentCategoryID,
            name as `Name`,
            description as `Description`,
            'Heading' as `DisplayAs`,
            rank as `Sort`
         from :_boards
        ");

        // Discussion.
        // A frequent issue is for the OPs content to be on the comment/post table, so you may need to join it.
        $discussion_Map = array(
            'Topic_ID' => 'DiscussionID',
            'Forum_ID' => 'CategoryID',
            'Author_ID' => 'InsertUserID',
            'Subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
        );
        // It's easier to convert between Unix time and MySQL datestamps during the db query.
        $ex->exportTable('Discussion', "
         select *,
            FROM_UNIXTIME(Message_date) as Message_date
         from :_tblTopic t
         join :_tblThread th
            on t.Start_Thread_ID = th.Thread_ID", $discussion_Map);

        // Comment.
        // This is where big migrations are going to get bogged down.
        // Be sure you have indexes created for any columns you are joining on.
        $comment_Map = array(
            'Thread_ID' => 'CommentID',
            'Topic_ID' => 'DiscussionID',
            'Author_ID' => 'InsertUserID',
            'IP_addr' => 'InsertIPAddress',
            'Message' => array('Column' => 'Body'),
            'Format' => 'Format',
            'Message_date' => array('Column' => 'DateInserted')
        );
        $ex->exportTable('Comment', "
         select th.*
         from :_tblThread th", $comment_Map);

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

// Closing PHP tag required. (make.php)
?>
