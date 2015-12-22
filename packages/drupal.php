<?php
/**
 * Vanilla 2 exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['drupal'] = array('name' => 'Drupal 6', 'prefix' => '');
$supported['drupal']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'Signatures' => 1,
    'Passwords' => 1,
);

class Drupal extends ExportController {

    /** @var array Required tables => columns */
    protected $_sourceTables = array();

    /**
     * @param ExportModel $Ex
     */
    protected function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('comment');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Begin
        $ex->beginExport('', 'Drupal');

        // Users
        $user_Map = array(
            'uid' => 'UserID',
            'name' => 'Name',
            'Password' => 'Password',
            'mail' => 'Email',
            'photo' => 'Photo',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'login' => array('Column' => 'DateLastActive', 'Filter' => 'TimestampToDate')
        );
        $ex->exportTable('User', "
         select u.*,
            nullif(concat('drupal/', u.picture), 'drupal/') as photo,
            concat('md5$$', u.pass) as Password,
            'Django' as HashMethod
         from :_users u
         where uid > 0", $user_Map);

        // Signatures.
        $userMeta_Map = array(
            'uid' => 'UserID',
            'Name' => 'Name',
            'signature' => 'Value'
        );
        $ex->exportTable('UserMeta', "
         select u.*, 'Plugins.Signatures.Sig' as Name
         from :_users u
         where uid > 0", $userMeta_Map);

        // Roles.
        $role_Map = array(
            'rid' => 'RoleID',
            'name' => 'Name'
        );
        $ex->exportTable('Role', "select r.* from :_role r", $role_Map);

        // User Role.
        $userRole_Map = array(
            'uid' => 'UserID',
            'rid' => 'RoleID'
        );
        $ex->exportTable('UserRole', "
         select * from :_users_roles", $userRole_Map);

        // Categories (sigh)
        $category_Map = array(
            'tid' => 'CategoryID',
            'name' => 'Name',
            'description' => 'description',
            'parent' => 'ParentCategoryID'
        );
        $ex->exportTable('Category', "
         select t.*, nullif(h.parent, 0) as parent
         from :_term_data t
         join :_term_hierarchy h
            on t.tid = h.tid", $category_Map);

        // Discussions.
        $discussion_Map = array(
            'nid' => 'DiscussionID',
            'title' => 'Name',
            'body' => 'Body',
            'uid' => 'InsertUserID',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'DateUpdated' => array('Column' => 'DateUpdated', 'Filter' => 'TimestampToDate'),
            'sticky' => 'Announce',
            'tid' => 'CategoryID'
        );
        $ex->exportTable('Discussion', "
         select n.*, nullif(n.changed, n.created) as DateUpdated, f.tid, r.body
         from nodeforum f
         left join node n
            on f.nid = n.nid
         left join node_revisions r
            on r.nid = n.nid", $discussion_Map);

        // Comments.
        $comment_Map = array(
            'cid' => 'CommentID',
            'uid' => 'InsertUserID',
            'body' => array('Column' => 'Body'),
            'hostname' => 'InsertIPAddress',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate')
        );
        $ex->exportTable('Comment', "
         select
            n.created,
            n.uid,
            r.body,
            c.nid as DiscussionID,
            n.title,
            'Html' as Format,
            nullif(n.changed, n.created) as DateUpdated
         from node n
         left join node_comments c
            on c.cid = n.nid
         left join node_revisions r
            on r.nid = n.nid
         where n.type = 'forum_reply'", $comment_Map);

        // Comments.
        /*$Comment_Map = array(
            'cid' => 'CommentID',
            'nid' => 'DiscussionID',
            'uid' => 'InsertUserID',
            'comment' => array('Column' => 'Body'),
            'hostname' => 'InsertIPAddress',
            'timeatamp' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate')
        );
        $Ex->ExportTable('Comment', "
           select c.*,
              n.title,
              'Html' as Format
           from comments c
           join node n
              on c.nid = n.nid", $Comment_Map);
        */
        // Media.
        /*$Media_Map = array(
            'fid' => 'MediaID',
            'nid' => 'ForeignID',
            'filename' => 'Name',
            'path' => 'Path',
            'filemime' => 'Type',
            'filesize' => 'Size',
            'uid' => 'InsertUserID',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate')
        );
        $Ex->ExportTable('Media', "
           select f.*,
              nullif(concat('drupal/', f.filepath), 'drupal/') as path,
              n.uid,
              n.created,
              'discussion' as ForeignTable
           from files f
           join node n
              on f.nid = n.nid
           where n.type = 'forum'", $Media_Map);
        */

        $ex->endExport();
    }

    /**
     *
     * @param ExportModel $Ex
     * @param string $TableName
     */
    protected function exportTable($ex, $tableName) {
        // Make sure the table exists.
        if (!$ex->exists($tableName)) {
            return;
        }

        $ex->exportTable($tableName, "select * from :_{$tableName}");
    }

}

// Closing PHP tag required. (make.php)
?>
