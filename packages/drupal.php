<?php
/**
 * Vanilla 2 exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['drupal'] = array('name' => 'Drupal 6', 'prefix' => '');
$Supported['drupal']['features'] = array(
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
    protected $_SourceTables = array();

    /**
     * @param ExportModel $Ex
     */
    protected function forumExport($Ex) {

        $CharacterSet = $Ex->getCharacterSet('comment');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        // Begin
        $Ex->beginExport('', 'Drupal');

        // Users
        $User_Map = array(
            'uid' => 'UserID',
            'name' => 'Name',
            'Password' => 'Password',
            'mail' => 'Email',
            'photo' => 'Photo',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'login' => array('Column' => 'DateLastActive', 'Filter' => 'TimestampToDate')
        );
        $Ex->exportTable('User', "
         select u.*,
            nullif(concat('drupal/', u.picture), 'drupal/') as photo,
            concat('md5$$', u.pass) as Password,
            'Django' as HashMethod
         from :_users u
         where uid > 0", $User_Map);

        // Signatures.
        $UserMeta_Map = array(
            'uid' => 'UserID',
            'Name' => 'Name',
            'signature' => 'Value'
        );
        $Ex->exportTable('UserMeta', "
         select u.*, 'Plugins.Signatures.Sig' as Name
         from :_users u
         where uid > 0", $UserMeta_Map);

        // Roles.
        $Role_Map = array(
            'rid' => 'RoleID',
            'name' => 'Name'
        );
        $Ex->exportTable('Role', "select r.* from :_role r", $Role_Map);

        // User Role.
        $UserRole_Map = array(
            'uid' => 'UserID',
            'rid' => 'RoleID'
        );
        $Ex->exportTable('UserRole', "
         select * from :_users_roles", $UserRole_Map);

        // Categories (sigh)
        $Category_Map = array(
            'tid' => 'CategoryID',
            'name' => 'Name',
            'description' => 'description',
            'parent' => 'ParentCategoryID'
        );
        $Ex->exportTable('Category', "
         select t.*, nullif(h.parent, 0) as parent
         from :_term_data t
         join :_term_hierarchy h
            on t.tid = h.tid", $Category_Map);

        // Discussions.
        $Discussion_Map = array(
            'nid' => 'DiscussionID',
            'title' => 'Name',
            'body' => 'Body',
            'uid' => 'InsertUserID',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'DateUpdated' => array('Column' => 'DateUpdated', 'Filter' => 'TimestampToDate'),
            'sticky' => 'Announce',
            'tid' => 'CategoryID'
        );
        $Ex->exportTable('Discussion', "
         select n.*, nullif(n.changed, n.created) as DateUpdated, f.tid, r.body
         from nodeforum f
         left join node n
            on f.nid = n.nid
         left join node_revisions r
            on r.nid = n.nid", $Discussion_Map);

        // Comments.
        $Comment_Map = array(
            'cid' => 'CommentID',
            'uid' => 'InsertUserID',
            'body' => array('Column' => 'Body'),
            'hostname' => 'InsertIPAddress',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate')
        );
        $Ex->exportTable('Comment', "
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
         where n.type = 'forum_reply'", $Comment_Map);

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

        $Ex->endExport();
    }

    /**
     *
     * @param ExportModel $Ex
     * @param string $TableName
     */
    protected function exportTable($Ex, $TableName) {
        // Make sure the table exists.
        if (!$Ex->exists($TableName)) {
            return;
        }

        $Ex->exportTable($TableName, "select * from :_{$TableName}");
    }

}

?>
