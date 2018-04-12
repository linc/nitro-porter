<?php
/**
 * Codoforum exporter tool. Tested with CodoForum v3.7.
 *
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author HansAdema
 * @package VanillaPorter
 */

$supported['codoforum'] = array(
    'name'=> 'CodoForum',
    'prefix' => 'codo_',
    'features' => array(
        'Comments' => 1,
        'Discussions' => 1,
        'Users' => 1,
        'Categories' => 1,
        'Roles' => 1,
        'Passwords' => 1,
        'Avatars' => 0,
        'PrivateMessages' => 0,
        'Signatures' => 1,
        'Attachments' => 0,
        'Bookmarks' => 0,
        'Permissions' => 0,
        'UserNotes' => 0,
    ),
);

class Codoforum extends ExportController {
    /** @var array Required tables => columns */
    protected $sourceTables = array(
        'users' => array('id', 'username', 'mail', 'user_status', 'pass', 'signature'),
        'roles' => array('rid', 'rname'),
        'user_roles' => array('uid', 'rid'),
        'categories' => array('cat_id', 'cat_name'),
        'topics' => array('topic_id', 'cat_id', 'uid', 'title'),
        'posts' => array('post_id', 'topic_id', 'uid', 'imessage'),
    );

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see $_structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('codo_posts');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->beginExport('', 'CodoForum');

        // User.
        $ex->exportTable('User', "
            select 
                u.id as UserID, 
                u.username as Name, 
                u.mail as Email, 
                u.user_status as Verified, 
                u.pass as Password, 
                'Vanilla' as HashMethod,
                from_unixtime(u.created) as DateFirstVisit
            from :_users u
         ");

        // Role.
        $ex->exportTable('Role', "
            select 
                r.rid as RolesID,
                r.rname as Name
            from :_roles r
        ");

        // User Role.
        $ex->exportTable('UserRole', "
            select 
                ur.uid as UserID,
                ur.rid as RoleID
            from :_user_roles ur
            where ur.is_primary = 1
        ");

        // UserMeta.
        $ex->exportTable('UserMeta', "
            select
                u.id as UserID,
                'Plugin.Signatures.Sig' as Name,
                u.signature as Value
            from :_users u
            where u.signature != '' and u.signature is not null"
        );

        // Category.
        $ex->exportTable('Category', "
            select 
                c.cat_id as CategoryID,
                c.cat_name as Name
            from :_categories c
        ");

        // Discussion.
        $ex->exportTable('Discussion', "
            select
                t.topic_id as DiscussionID,
                t.cat_id as CategoryID,
                t.uid as InsertUserID,
                t.title as Name,
                from_unixtime(t.topic_created) as DateInserted,
                from_unixtime(t.last_post_time) as DateLastComment
            from :_topics t
        ");

        // Comment.
        $ex->exportTable('Comment', "
            select
                p.post_id as CommentID,
                p.topic_id as DiscussionID,
                p.uid as InsertUserID,
                p.imessage as Body,
                'Markdown' as Format,
                from_unixtime(p.post_created) as DateInserted
            from :_posts p
        ");

        // TODO UserDiscussion.
        // This is the table for assigning bookmarks/subscribed threads.

        // TODO Media.
        // Attachment data goes here. Vanilla attachments are files under the /uploads folder.
        // This is usually the trickiest step because you need to translate file paths.
        // If you need to export blobs from the database, see the vBulletin porter.

        // TODO Conversations.
        // Private messages often involve the most data manipulation.
        // If you need a large number of complex SQL statements, consider making it a separate method
        // to keep the main process easy to understand. Pass $ex as a parameter if you do.

        $ex->endExport();
    }
}

// Closing PHP tag required. (make.php)
?>
