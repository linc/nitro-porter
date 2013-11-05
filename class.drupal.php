<?php
/**
 * Vanilla 2 exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['drupal'] = array('name'=> 'Drupal 6', 'prefix'=>'');
 
class Drupal extends ExportController {

   /** @var array Required tables => columns */  
   protected $_SourceTables = array();
   
   /**
    * @param ExportModel $Ex
    */
   protected function ForumExport($Ex) {
      $this->Ex = $Ex;

      // Get the characterset for the comments.
      $CharacterSet = $Ex->GetCharacterSet('comment');
      if ($CharacterSet)
         $Ex->CharacterSet = $CharacterSet;
      
      $T2D = array($Ex, 'TimestampToDate');

      // Begin
      $Ex->BeginExport('', 'Drupal');
      
      // Users
      $User_Map = array(
         'uid'=>'UserID',
         'name'=>'Name',
         'Password'=>'Password',
         'mail'=>'Email',
         'photo'=>'Photo',
         'created'=>array('Column' => 'DateInserted', 'Filter' => $T2D),
         'login'=>array('Column' => 'DateLastActive', 'Filter' => $T2D)
      );   
      $Ex->ExportTable('User', "
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
          'signature' => 'Value');
      $Ex->ExportTable('UserMeta', "
         select u.*, 'Plugins.Signatures.Sig' as Name
         from :_users u
         where uid > 0", $UserMeta_Map);
      
      // Roles.
      $Role_Map = array(
          'rid' => 'RoleID',
          'name' => 'Name');
      $Ex->ExportTable('Role', "select r.* from :_role r", $Role_Map);
      
      // User Role.
      $UserRole_Map = array(
          'uid' => 'UserID',
          'rid' => 'RoleID');
      $Ex->ExportTable('UserRole', "
         select * from :_users_roles", $UserRole_Map);
      
      // Categories (sigh)
      $Category_Map = array(
          'tid' => 'CategoryID',
          'name' => 'Name',
          'description' => 'description',
          'parent' => 'ParentCategoryID');
      $Ex->ExportTable('Category', "
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
          'created' => array('Column' => 'DateInserted', 'Filter' => $T2D),
          'DateUpdated' => array('Column' => 'DateUpdated', 'Filter' => $T2D),
          'sticky' => 'Announce',
          'tid' => 'CategoryID'
      );
      $Ex->ExportTable('Discussion', "
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
          'created' => array('Column' => 'DateInserted', 'Filter' => $T2D)
      );
      $Ex->ExportTable('Comment', "
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
         where n.type = 'comment'", $Comment_Map);
      
      // Comments.
      /*$Comment_Map = array(
          'cid' => 'CommentID',
          'nid' => 'DiscussionID',
          'uid' => 'InsertUserID',
          'comment' => array('Column' => 'Body'),
          'hostname' => 'InsertIPAddress',
          'timeatamp' => array('Column' => 'DateInserted', 'Filter' => $T2D)
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
          'created' => array('Column' => 'DateInserted', 'Filter' => $T2D)
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
      
      $Ex->EndExport();
   }

   /**
    *
    * @param ExportModel $Ex
    * @param string $TableName
    */
   protected function ExportTable($Ex, $TableName) {
      // Make sure the table exists.
      if (!$Ex->Exists($TableName))
         return;

      $Ex->ExportTable($TableName, "select * from :_{$TableName}");
   }
   
}
?>