<?php
/**
 * Punbb exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

class Punbb extends ExportController {

   /** @var array Required tables => columns */  
   public $SourceTables = array(
      );
   
   /**
    * Forum-specific export format
    * @todo Project file size / export time and possibly break into multiple files
    * @param ExportModel $Ex
    * 
    */
   protected function ForumExport($Ex) {

      $Ex->BeginExport('', 'PunBB 1.*', array('HashMethod' => 'punbb'));
      
      // User.
      $User_Map = array(
          'id' => 'UserID',
          'username' => 'Name',
          'email' => 'Email',
          'timezone' => 'HourOffset',
          'registration_ip' => 'InsertIPAddress',
          'PasswordHash' => 'Password');
      $Ex->ExportTable('User', "
         select 
           u.*, 
           concat(u.password, '$', u.salt) as PasswordHash, 
           from_unixtime(registered) as DateInserted, 
           from_unixtime(last_visit) as DateLastActive
         from :_users u
         where group_id <> 2", $User_Map);
      
      // Role.
      $Role_Map = array(
          'g_id' => 'RoleID',
          'g_title' => 'Name'
          );
      $Ex->ExportTable('Role', "select * from :_groups", $Role_Map);
      
      // Permission.
      $Permission_Map = array(
          'g_id' => 'RoleID',
          'g_modertor' => 'Garden.Moderation.Manage',
          'g_mod_edit_users' => 'Garden.Users.Edit',
          'g_mod_rename_users' => 'Garden.Users.Delete',
          'g_read_board' => 'Vanilla.Discussions.View',
          'g_view_users' => 'Garden.Profiles.View',
          'g_post_topics' => 'Vanilla.Discussions.Add',
          'g_post_replies' => 'Vanilla.Comments.Add',
          'g_pun_attachment_allow_download' => 'Plugins.Attachments.Download.Allow',
          'g_pun_attachment_allow_upload' => 'Plugins.Attachments.Upload.Allow',
          
          );
      $Permission_Map = $Ex->FixPermissionColumns($Permission_Map);
      $Ex->ExportTable('Permission', "
      select
         g.*,
         g_post_replies as `Garden.SignIn.Allow`,
         g_mod_edit_users as `Garden.Users.Add`,
         case when g_title = 'Administrators' then 'All' else null end as _Permissions
      from :_groups g", $Permission_Map);
      
      // UserRole.
      $UserRole_Map = array(
          'id' => 'UserID',
          'group_id' => 'RoleID'
      );
      $Ex->ExportTable('UserRole',
         "select
            case u.group_id when 2 then 0 else id end as id,
            u.group_id
          from :_users u", $UserRole_Map);
      
      // Signatures.
      $Ex->ExportTable('UserMeta', "
         select
         id,
         'Plugin.Signatures.Sig' as Name,
         signature
      from :_users u
      where u.signature is not null", array('id ' => 'UserID', 'signature' => 'Value'));
      
      
      // Category.
      $Category_Map = array(
          'id' => 'CategoryID',
          'forum_name' => 'Name',
          'forum_desc' => 'Description',
          'disp_position' => 'Sort',
          'parent_id' => 'ParentCategoryID'
          );
      $Ex->ExportTable('Category', "
      select
        id,
        forum_name,
        forum_desc,
        disp_position,
        cat_id * 1000 as parent_id
      from punbb_forums f
      union

      select
        id * 1000,
        cat_name,
        '',
        disp_position,
        null
      from punbb_categories", $Category_Map);
      
      // Discussion.
      $Discussion_Map = array(
          'id' => 'DiscussionID',
          'poster_id' => 'InsertUserID',
          'poster_ip' => 'InsertIPAddress',
          'closed' => 'Closed',
          'sticky' => 'Announce',
          'forum_id' => 'CategoryID',
          'subject' => 'Name',
          'message' => 'Body'
          
          );
      $Ex->ExportTable('Discussion', "
      select t.*, 
        from_unixtime(p.posted) as DateInserted, 
        p.poster_id, 
        p.poster_ip,
        p.message,
        from_unixtime(p.edited) as DateUpdated, 
        eu.id as UpdateUserID,
        'BBCode' as Format
      from punbb_topics t
      left join punbb_posts p
        on t.first_post_id = p.id
      left join punbb_users eu
        on eu.username = p.edited_by", $Discussion_Map);
      
      // Comment.
      $Comment_Map = array(
          'id' => 'CommentID',
          'topic_id' => 'DiscussionID',
          'poster_id' => 'InsertUserID',
          'poster_ip' => 'InsertIPAddress',
          'message' => 'Body'
      );
      $Ex->ExportTable('Comment', "
            select p.*, 
        'BBCode' as Format,
        from_unixtime(p.posted) as DateInserted,
        from_unixtime(p.edited) as DateUpdated, 
        eu.id as UpdateUserID
      from punbb_topics t
      join punbb_posts p
        on t.id = p.topic_id
      left join punbb_users eu
        on eu.username = p.edited_by
      where p.id <> t.first_post_id;", $Comment_Map);
      
      // Tag.
      $Tag_Map = array(
          'id' => 'TagID',
          'tag' => 'Name');
      $Ex->ExportTable('Tag', "select * from :_tags", $Tag_Map);
      
      // TagDisucssion.
      $TagDiscussionMap = array(
          'topic_id' => 'DiscussionID',
          'tag_id' => 'TagID');
      $Ex->ExportTable('TagDiscussion', "select * from :_topic_tags", $TagDiscussionMap);
      
      // Media.
      $Media_Map = array(
         'id' => 'MediaID',
         'filename' => 'Name',
         'file_mime_type' => 'Type',
         'size' => 'Size',
         'owner_id' => 'InsertUserID'
       );
      $Ex->ExportTable('Media', "
      select f.*,
         concat('FileUpload/', f.file_path) as Path,
         from_unixtime(f.uploaded_at) as DateInserted,
         case when post_id is null then 'Discussion' else 'Comment' end as ForeignTable,
         coalesce(post_id, topic_id) as ForieignID
      from :_attach_files f", $Media_Map);
      
      
      // End
      $Ex->EndExport();
   }

   function StripMediaPath($AbsPath) {
      if (($Pos = strpos($AbsPath, '/uploads/')) !== FALSE)
         return substr($AbsPath, $Pos + 9);
      return $AbsPath;
   }

   function FilterPermissions($Permissions, $ColumnName, &$Row) {
      $Permissions2 = unserialize($Permissions);

      foreach ($Permissions2 as $Name => $Value) {
         if (is_null($Value))
            $Permissions2[$Name] = FALSE;
      }

      if (is_array($Permissions2)) {
         $Row = array_merge($Row, $Permissions2);
         $this->Ex->CurrentRow = $Row;
         return isset($Permissions2['PERMISSION_ADD_COMMENTS']) ? $Permissions2['PERMISSION_ADD_COMMENTS'] : FALSE;
      }
      return FALSE;
   }

   function ForceBool($Value) {
      if ($Value)
         return TRUE;
      return FALSE;
   }
}
?>
