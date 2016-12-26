<?php
/**
 * Codoforum exporter tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
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
    protected $sourceTables = array(
        'users' => array('id', 'name', 'mail', 'user_status', 'pass', 'signature'),
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
     */
    public function forumExport($ex) {
        $characterSet = $ex->getCharacterSet('codo_posts');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->beginExport('', 'CodoForum');

        // User.
        $user_Map = array(
            'id' => 'UserID',
            'name' => 'Name',
            'mail' => 'Email',
            'user_status' => 'Verified',
            'pass' => 'Password',
        );
        $ex->exportTable('User', "
         select u.*, FROM_UNIXTIME(created) as DateFirstVisit
         from :_users u
         ", $user_Map);

        // Role.
        $role_Map = array(
            'rid' => 'RoleID',
            'rname' => 'Name', // We let these arrays end with a comma to prevent typos later as we add.
        );
        $ex->exportTable('Role', "
         select *
         from :_roles", $role_Map);

        // User Role.
        $userRole_Map = array(
            'uid' => 'UserID',
            'rid' => 'RoleID',
        );
        $ex->exportTable('UserRole', "
         select u.*
         from :_user_roles u
         where is_primary = 1", $userRole_Map);

        // TODO Permission.

        // UserMeta.
        $ex->exportTable('UserMeta', "
         select
            id as UserID,
            'Plugin.Signatures.Sig' as `Name`,
            signature as `Value`
         from :_users
         where signature != '' AND signature IS NOT NULL");

        // Category.
        $category_Map = array(
            'cat_id' => 'CategoryID',
            'cat_name' => 'Name',
        );
        $ex->exportTable('Category', "
         select *
         from :_categories c", $category_Map);

        // Discussion.
        $discussion_Map = array(
            'topic_id' => 'DiscussionID',
            'cat_id' => 'CategoryID',
            'uid' => 'InsertUserID',
            'title' => 'Name',
        );
        $ex->exportTable('Discussion', "
         select *, FROM_UNIXTIME(topic_created) as Message_date, FROM_UNIXTIME(last_post_time) as DateLastComment
         from :_topics t", $discussion_Map);

        // Comment.
        $comment_Map = array(
            'post_id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'uid' => 'InsertUserID',
            'imessage' => array('Column' => 'Body'),
        );
        $ex->exportTable('Comment', "
         select th.*, 'Markdown' as Format, FROM_UNIXTIME(post_created) as Message_date
         from :_posts th", $comment_Map);

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
