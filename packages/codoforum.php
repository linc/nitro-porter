<?php
/**
 * Codoforum exporter tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

// Add to the $Supported array so it appears in the dropdown menu. Uncomment next line.
$supported['codoforum'] = array('name'=> 'CodoForum', 'prefix'=>'codo_');

// Optionally, add the features you are supporting. Set all values to 1 or a string for support notes.
// See functions/feature-functions.php VanillaFeatureSet() for array keys.
//$Supported['samplename']['features'] = array('Users' => 1);

class Codoforum extends ExportController {
    /**
     * You can use this to require certain tables and columns be present.
     *
     * This can be useful for verifying data integrity. Don't specify more columns
     * than your porter actually requires to avoid forwards-compatibility issues.
     *
     * @var array Required tables => columns
     */
    protected $sourceTables = array(
//        'forums' => array(), // This just requires the 'forum' table without caring about columns.
//        'posts' => array(),
//        'topics' => array(),
//        'users' => array('ID', 'user_login', 'user_pass', 'user_email'), // Require specific cols on 'users'
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
        $characterSet = $ex->getCharacterSet('codo_posts');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Reiterate the platform name here to be included in the porter file header.
        $ex->beginExport('', 'CodoForum');

        // It's usually a good idea to do the porting in the approximate order laid out here.

        // User.
        // Map as much as possible using the $x_Map array for clarity.
        // Key is always the source column name.
        // Value is either the destination column or an array of meta data, usually Column & Filter.
        // If it's a meta array, 'Column' is the destination column name and 'Filter' is a method name to run it thru.
        // Here, 'HTMLDecoder' is a method in ExportModel. Check there for available filters.
        // Assume no filter is needed and only use one if you encounter issues.
        $user_Map = array(
            'id' => 'UserID',
            'name' => 'Name',
            'mail' => 'Email',
            'user_status' => 'Verified',
            'pass' => 'Password',
        );
        // This is the query that the x_Map array above will be mapped against.
        // Therefore, our select statement must cover all the "source" columns.
        // It's frequently necessary to add joins, where clauses, and more to get the data we want.
        // The :_ before the table name is the placeholder for the prefix designated. It gets swapped on the fly.
        $ex->exportTable('User', "
         select u.*, FROM_UNIXTIME(created) as DateFirstVisit
         from :_users u
         ", $user_Map);

        // Role.
        // The Vanilla roles table will be wiped by any import. If your current platform doesn't have roles,
        // you can hard code new ones into the select statement. See Vanilla's defaults for a good example.
        $role_Map = array(
            'rid' => 'RoleID',
            'rname' => 'Name', // We let these arrays end with a comma to prevent typos later as we add.
        );
        $ex->exportTable('Role', "
         select *
         from :_roles", $role_Map);

        // User Role.
        // Really simple matchup.
        // Note that setting Admin=1 on the User table trumps all roles & permissions with "owner" privileges.
        // Whatever account you select during the import will get the Admin=1 flag to prevent permissions issues.
        $userRole_Map = array(
            'uid' => 'UserID',
            'rid' => 'RoleID',
        );
        $ex->exportTable('UserRole', "
         select u.*
         from :_user_roles u
         where is_primary = 1", $userRole_Map);

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
            id as UserID,
            'Plugin.Signatures.Sig' as `Name`,
            signature as `Value`
         from :_users
         where signature != '' AND signature IS NOT NULL");

        // Category.
        // Be careful to not import hundreds of categories. Try translating huge schemas to Tags instead.
        // Numeric category slugs aren't allowed in Vanilla, so be careful to sidestep those.
        // Don't worry about rebuilding the TreeLeft & TreeRight properties. Vanilla can fix this afterward
        // if you just get the Sort and ParentIDs correct.
        $category_Map = array(
            'cat_id' => 'CategoryID',
            'cat_name' => 'Name',
        );
        $ex->exportTable('Category', "
         select *
         from :_categories c
         ", $category_Map);

        // Discussion.
        // A frequent issue is for the OPs content to be on the comment/post table, so you may need to join it.
        $discussion_Map = array(
            'topic_id' => 'DiscussionID',
            'cat_id' => 'CategoryID',
            'uid' => 'InsertUserID',
            'title' => 'Name',
        );
        // It's easier to convert between Unix time and MySQL datestamps during the db query.
        $ex->exportTable('Discussion', "
         select *,
            FROM_UNIXTIME(topic_created) as Message_date, FROM_UNIXTIME(last_post_time) as DateLastComment
         from :_topics t", $discussion_Map);

        // Comment.
        // This is where big migrations are going to get bogged down.
        // Be sure you have indexes created for any columns you are joining on.
        $comment_Map = array(
            'post_id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'uid' => 'InsertUserID',
            'imessage' => array('Column' => 'Body'),
        );
        $ex->exportTable('Comment', "
         select th.*, 'Markdown' as Format, 
            FROM_UNIXTIME(post_created) as Message_date
         from :_posts th", $comment_Map);

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
