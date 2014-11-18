<?php
/**
 * Invision Powerboard exporter tool.
 *
 * To export avatars, provide ?avatars=1&folder=/path/to/avatars
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license Proprietary
 * @package VanillaPorter
 */

$Supported['ipb'] = array('name' => 'Invision Powerboard (IPB) 3.*', 'prefix'=>'ibf_');
$Supported['ipb']['CommandLine'] = array(
   'folder' => array('Location of source avatars.', 'Sx' => ':', 'Field' => 'folder'),
   'source' => array('Source user table.', 'Sx' => ':', 'Field' => 'sourcetable'),
   'avatars' => array('Whether or not to export avatars.', 'Sx' => '::', 'Field' => 'avatars', 'Short' => 'a', 'Default' => ''),
);

class IPB extends ExportController {
   /**
    * Export avatars into vanilla-compatibles names
    */
   public function DoAvatars() {
      // Source table
      $SourceTable = GetValue('sourcetable', $_POST, 'profile_portal');
      
      // Check source folder
      $SourceFolder = $this->Param('folder');
      if (!is_dir($SourceFolder))
         trigger_error("Source avatar folder '{$SourceFolder}' does not exist.");
      
      // Set up a target folder
      $TargetFolder = CombinePaths(array($SourceFolder, 'ipb'));
      if (!is_dir($SourceFolder)) {
         @$Made = mkdir($TargetFolder, 0777, TRUE);
         if (!$Made) trigger_error("Target avatar folder '{$TargetFolder}' could not be created.");
      }
      
      $this->Ex->SourcePrefix = 'ibf_';
      
      switch ($SourceTable) {
         case 'profile_portal':
      
            $UserList = $this->Ex->Query("select 
                  pp_member_id as member_id,
                  pp_main_photo as main_photo,
                  pp_thumb_photo as thumb_photo,
                  coalesce(pp_main_photo,pp_thumb_photo,0) as photo
               from ibf_profile_portal
               where length(coalesce(pp_main_photo,pp_thumb_photo,0)) > 3
               order by pp_member_id asc");
            
            break;
         
         case 'member_extra':
            
            $UserList = $this->Ex->Query("select 
                  id as member_id,
                  avatar_location as photo
               from ibf_member_extra
               where 
                  length(avatar_location) > 3 and
                  avatar_location <> 'noavatar'
               order by id asc");

            break;
      }
      
      $Processed = 0;
      $Skipped = 0;
      $Completed = 0;
      $Errors = array();
      while (($Row = mysql_fetch_assoc($UserList)) !== FALSE) {
         $Processed++;
         $Error = FALSE;

         $UserID = $Row['member_id'];

         // Determine target paths and name
         $Photo = trim($Row['photo']);
         $Photo = preg_replace('`^upload:`', '', $Photo);
         if (preg_match('`^https?:`i', $Photo)) {
            $Skipped++;
            continue;
         }
         
         $PhotoFileName = basename($Photo);
         $PhotoPath = dirname($Photo);
         $PhotoFolder = CombinePaths(array($TargetFolder, $PhotoPath));
         @mkdir($PhotoFolder, 0777, TRUE);

         $PhotoSrc = CombinePaths(array($SourceFolder, $Photo));
         if (!file_exists($PhotoSrc)) {
            $Errors[] = "Missing file: {$PhotoSrc}";
            continue;
         }

         $MainPhoto = trim(GetValue('main_photo', $Row, NULL));
         $ThumbPhoto = trim(GetValue('thumb_photo', $Row, NULL));

         // Main Photo
         if (!$MainPhoto) $MainPhoto = $Photo;
         $MainSrc = CombinePaths(array($SourceFolder, $MainPhoto));
         $MainDest = CombinePaths(array($PhotoFolder, "p".$PhotoFileName));
         $Copied = @copy($MainSrc, $MainDest);
         if (!$Copied) {
            $Error |= TRUE;
            $Errors[] = "! failed to copy main photo '{$MainSrc}' for user {$UserID} (-> {$MainDest}).";
         }

         // Thumb Photo
         if (!$ThumbPhoto) $ThumbPhoto = $Photo;
         $ThumbSrc = CombinePaths(array($SourceFolder, $MainPhoto));
         $ThumbDest = CombinePaths(array($PhotoFolder,"n".$PhotoFileName));
         $Copied = @copy($ThumbSrc, $ThumbDest);
         if (!$Copied) {
            $Error |= TRUE;
            $Errors[] = "! failed to copy thumbnail '{$ThumbSrc}' for user {$UserID} (-> {$ThumbDest}).";
         }

         if (!$Error) $Completed++;
         
         if (!($Processed % 100))
            echo " - processed {$Processed}\n";
      }

      $nErrors = sizeof($Errors);
      if ($nErrors) {
         echo "{$nErrors} errors:\n";
         foreach ($Errors as $Error)
            echo "{$Error}\n";
      }
      
      echo "Completed: {$Completed}\n";
      echo "Skipped: {$Skipped}\n";
   }
   
   /**
    * @param ExportModel $Ex 
    */
   protected function ForumExport($Ex) {
//      $Ex->TestMode = FALSE;
//      $Ex->TestLimit = FALSE;
//      $Ex->Destination = 'database';
//      $Ex->DestDb = 'unknownworlds';
//      $Ex->CaptureOnly = TRUE;
//      $Ex->ScriptCreateTable = FALSE;
//      $Ex->DestPrefix = 'GDN_';
      
      $Ex->SourcePrefix = 'ibf_';
      
      // Get the characterset for the comments.
      $CharacterSet = $Ex->GetCharacterSet('posts');
      if ($CharacterSet)
         $Ex->CharacterSet = $CharacterSet;
      
      // Decode all of the necessary fields.
//      $Ex->HTMLDecoderDb('members', 'members_display_name', 'member_id');
//      $Ex->HTMLDecoderDb('members', 'name', 'member_id');
//      $Ex->HTMLDecoderDb('members', 'title', 'member_id');
//      $Ex->HtmlDecoderDb('groups', 'g_title', 'g_id');
//      $Ex->HtmlDecoderDb('topics', 'title', 'tid');
//      $Ex->HtmlDecoderDb('topics', 'description', 'tid');
      
      // Begin
      $Ex->BeginExport('', 'IPB 3.*', array('HashMethod' => 'ipb'));

      // Export avatars
      if ($this->Param('avatars')) {
         $this->DoAvatars();
      }
      
      if ($Ex->Exists('members', 'member_id') === TRUE) {
         $MemberID = 'member_id';
      } else {
         $MemberID = 'id';
      }
      
      // Users.
      $User_Map = array(
         $MemberID => 'UserID',
         'members_display_name' => array('Column' => 'Name', 'Filter' => 'HtmlDecoder'),
         'email' => 'Email',
         'joined' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
         'firstvisit' => array('Column' => 'DateFirstVisit', 'SourceColumn' => 'joined', 'Filter' => 'TimestampToDate'),
         'ip_address' => 'InsertIPAddress',
         'title' => 'Title',
         'time_offset' => 'HourOffset',
         'last_activity' => array('Column' => 'DateLastActive', 'Filter' => 'TimestampToDate'),
         'member_banned' => 'Banned',
         'Photo' => 'Photo',
         'title' => 'Title',
         'location' => 'Location'
      );
      
      $From = '';
      $Select = '';
      
      if ($Ex->Exists('members', 'members_pass_hash') === true) {
         $Select = ",concat(m.members_pass_hash, '$', m.members_pass_salt) as Password";
      } else {
         $Select = ",concat(mc.converge_pass_hash, '$', mc.converge_pass_salt) as Password";
         $From = "left join ibf_members_converge mc
            on m.$MemberID = mc.converge_id";
      }
      
      if ($Ex->Exists('members', 'hide_email') === true) {
         $ShowEmail = '!hide_email';
      } else {
         $ShowEmail = '0';
      }
      
      $Cdn = $this->CdnPrefix();
      
      if ($Ex->Exists('member_extra') === TRUE) {
         $Sql = "select
                  m.*,
                  m.joined as firstvisit,
                  'ipb' as HashMethod,
                  $ShowEmail as ShowEmail,
                  case when x.avatar_location in ('noavatar', '') then null
                     when x.avatar_location like 'upload:%' then concat('{$Cdn}ipb/', right(x.avatar_location, length(x.avatar_location) - 7))
                     when x.avatar_type = 'upload' then concat('{$Cdn}ipb/', x.avatar_location)
                     when x.avatar_type = 'url' then x.avatar_location
                     when x.avatar_type = 'local' then concat('{$Cdn}style_avatars/', x.avatar_location)
                     else null
                  end as Photo,
                  x.location
                  $Select
                 from ibf_members m
                 left join ibf_member_extra x
                  on m.$MemberID = x.id
                 $From";
      } else {
         $Sql = "select
                  m.*,
                  joined as firstvisit,
                  'ipb' as HashMethod,
                  $ShowEmail as ShowEmail,
                  case when length(p.pp_main_photo) <= 3 or p.pp_main_photo is null then null
                     when p.pp_main_photo like '%//%' then p.pp_main_photo
                     else concat('{$Cdn}ipb/', p.pp_main_photo)
                  end as Photo
                 $Select
                 from ibf_members m
                 left join ibf_profile_portal p
                    on m.$MemberID = p.pp_member_id
                 $From";
      }
      $this->ClearFilters('members', $User_Map, $Sql, 'm');
      $Ex->ExportTable('User', $Sql, $User_Map);  // ":_" will be replaced by database prefix
      
      // Roles.
      $Role_Map = array(
          'g_id' => 'RoleID',
          'g_title' => 'Name'
      );
      $Ex->ExportTable('Role', "select * from ibf_groups", $Role_Map);
      
      // Permissions.
      $Permission_Map = array(
          'g_id' => 'RoleID',
          'g_view_board' => 'Garden.SignIn.Allow',
          'g_view_board2' => 'Garden.Profiles.View',
          'g_view_board3' => 'Garden.Activity.View',
          'g_view_board4' => 'Vanilla.Discussions.View',
          'g_edit_profile' => 'Garden.Profiles.Edit',
          'g_post_new_topics' => 'Vanilla.Discussions.Add',
          'g_reply_other_topics' => 'Vanilla.Comments.Add',
//          'g_edit_posts' => 'Vanilla.Comments.Edit', // alias
          'g_open_close_posts' => 'Vanilla.Discussions.Close',
          'g_is_supmod' => 'Garden.Moderation.Manage',
          'g_access_cp' => 'Garden.Settings.View',
//          'g_edit_topic' => 'Vanilla.Discussions.Edit'
      );
      $Permission_Map = $Ex->FixPermissionColumns($Permission_Map);
      $Ex->ExportTable('Permission', "
         select r.*,
            r.g_view_board as g_view_board2,
            r.g_view_board as g_view_board3,
            r.g_view_board as g_view_board4
         from ibf_groups r", $Permission_Map);
      
      // User Role.
      
      if ($Ex->Exists('members', 'member_group_id') === TRUE)
         $GroupID = 'member_group_id';
      else
         $GroupID = 'mgroup';
      
      $UserRole_Map = array(
          $MemberID => 'UserID',
          $GroupID => 'RoleID'
      );
      
      $Sql = "
         select
            m.$MemberID, m.$GroupID
         from ibf_members m";
      
      if ($Ex->Exists('members', 'mgroup_others')) {
         $Sql .= "
            union all
            
            select m.$MemberID, g.g_id
            from ibf_members m
            join ibf_groups g
               on find_in_set(g.g_id, m.mgroup_others)";

      }
      
      $Ex->ExportTable('UserRole', $Sql, $UserRole_Map);
      
      // UserMeta.
      $UserMeta_Map = array(
          'UserID' => 'UserID',
          'Name' => 'Name',
          'Value' => 'Value'
          );
      
      if ($Ex->Exists('profile_portal', 'signature') === TRUE) {
         $Sql = "
         select
            pp_member_id as UserID,
            'Plugin.Signatures.Sig' as Name,
            signature as Value
         from ibf_profile_portal
         where length(signature) > 1

         union all

         select
            pp_member_id as UserID,
            'Plugin.Signatures.Format' as Name,
            'IPB' as Value
         from ibf_profile_portal
         where length(signature) > 1
               ";
      } elseif ($Ex->Exists('member_extra', array('id', 'signature')) === TRUE) {
         $Sql = "
         select
            id as UserID,
            'Plugin.Signatures.Sig' as Name,
            signature as Value
         from ibf_member_extra
         where length(signature) > 1

         union all

         select
            id as UserID,
            'Plugin.Signatures.Format' as Name,
            'IPB' as Value
         from ibf_member_extra
         where length(signature) > 1";
      } else {
         $Sql = FALSE;
      }
      if ($Sql)
         $Ex->ExportTable('UserMeta', $Sql, $UserMeta_Map);
      
      // Category.
      $Category_Map = array(
          'id' => 'CategoryID',
          'name' => array('Column' => 'Name', 'Filter' => 'HtmlDecoder'),
          'name_seo' => 'UrlCode',
          'description' => 'Description',
          'parent_id' => 'ParentCategoryID',
          'position' => 'Sort'
          );
      $Ex->ExportTable('Category', "select * from ibf_forums", $Category_Map);
      
      // Discussion.
      $Discussion_Map = array(
          'tid' => 'DiscussionID',
          'title' => 'Name',
          'description' => array('Column' => 'SubName', 'Type' => 'varchar(255)'),
          'forum_id' => 'CategoryID',
          'starter_id' => 'InsertUserID',
          'start_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
          'ip_address' => 'InsertIPAddress',
          'edit_time' => array('Column' => 'DateUpdated', 'Filter' => 'TimestampToDate'),
//          'last_post' => array('Column' => 'DateLastPost', 'Filter' => array($Ex, 'TimestampToDate')),
          'posts' => 'CountComments',
          'views' => 'CountViews',
          'pinned' => 'Announce',
          'post' => 'Body',
          'closed' => 'Closed'
          );
      $Sql = "
      select 
         t.*,
         case 
            when t.description <> '' and p.post is not null then concat('<div class=\"IPBDescription\">', t.description, '</div>', p.post)
            when t.description <> '' then t.description
            else p.post
         end as post,
         case when t.state = 'closed' then 1 else 0 end as closed,
         'IPB' as Format,
         p.ip_address,
         p.edit_time
      from ibf_topics t
      left join ibf_posts p
         on t.topic_firstpost = p.pid
      where t.tid between {from} and {to}";
      $this->ClearFilters('topics', $Discussion_Map, $Sql, 't');
      $Ex->ExportTable('Discussion', $Sql, $Discussion_Map);
      
      // Comments.
      $Comment_Map = array(
          'pid' => 'CommentID',
          'topic_id' => 'DiscussionID',
          'author_id' => 'InsertUserID',
          'ip_address' => 'InsertIPAddress',
          'post_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
          'edit_time' => array('Column' => 'DateUpdated', 'Filter' => 'TimestampToDate'),
          'post' => 'Body'
          );
      $Sql = "
      select
         p.*,
         'IPB' as Format
      from ibf_posts p
      join ibf_topics t
         on p.topic_id = t.tid
      where p.pid between {from} and {to}
         and p.pid <> t.topic_firstpost";
      $this->ClearFilters('Comment', $Comment_Map, $Sql, 'p');
      $Ex->ExportTable('Comment', $Sql, $Comment_Map);
      
      // Media.
      $Media_Map = array(
          'attach_id' => 'MediaID',
          'atype_mimetype' => 'Type',
          'attach_file' => 'Name',
          'attach_path' => 'Path',
          'attach_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
          'attach_member_id' => 'InsertUserID',
          'attach_filesize' => 'Size',
          'ForeignID' => 'ForeignID',
          'ForeignTable' => 'ForeignTable',
          'StorageMethod' => 'StorageMethod',
          'img_width' => 'ImageWidth',
          'img_height' => 'ImageHeight'
      );
      $Sql = "select 
   a.*, 
   concat('~cf/ipb/a.attach_location') as attach_path,
   ty.atype_mimetype,
   case when p.pid = t.topic_firstpost then 'discussion' else 'comment' end as ForeignTable,
   case when p.pid = t.topic_firstpost then t.tid else p.pid end as ForeignID,
   case a.attach_img_width when 0 then a.attach_thumb_width else a.attach_img_width end as img_width,
   case a.attach_img_height when 0 then a.attach_thumb_height else a.attach_img_height end as img_height,
   'local' as StorageMethod
from ibf_attachments a
join ibf_posts p
   on a.attach_rel_id = p.pid and a.attach_rel_module = 'post'
join ibf_topics t
   on t.tid = p.topic_id
left join ibf_attachments_type ty
   on a.attach_ext = ty.atype_extension";
      $this->ClearFilters('Media', $Media_Map, $Sql);
      $Ex->ExportTable('Media', $Sql, $Media_Map);
      
      if ($Ex->Exists('message_topic_user_map')) {
         $this->_ExportConversationsV3();
      } else {
         $this->_ExportConversationsV2();
      }
      
      $Ex->EndExport();
   }
   
   protected function _ExportConversationsV2() {
      $Ex = $this->Ex;
      
      $Sql = <<<EOT
create table tmp_to (
   id int,
   userid int,
   primary key (id, userid)
);

truncate table tmp_to;

insert ignore tmp_to (
   id,
   userid
)
select
   mt_id,
   mt_from_id
from ibf_message_topics;

insert ignore tmp_to (
   id,
   userid
)
select
   mt_id,
   mt_to_id
from ibf_message_topics;

create table tmp_to2 (
   id int primary key,
   userids varchar(255)
);
truncate table tmp_to2;

insert tmp_to2 (
   id,
   userids
)
select
   id,
   group_concat(userid order by userid)
from tmp_to
group by id;

create table tmp_conversation (
   id int primary key,
   title varchar(255),
   title2 varchar(255),
   userids varchar(255),
   groupid int
);

replace tmp_conversation (
   id,
   title,
   title2,
   userids
)
select 
   mt_id,
   mt_title,
   mt_title,
   t2.userids
from ibf_message_topics t
join tmp_to2 t2
   on t.mt_id = t2.id;

update tmp_conversation
set title2 = trim(right(title2, length(title2) - 3))
where title2 like 'Re:%';

update tmp_conversation
set title2 = trim(right(title2, length(title2) - 5))
where title2 like 'Sent:%';

update tmp_conversation
set title2 = trim(right(title2, length(title2) - 3))
where title2 like 'Re:%';

update tmp_conversation
set title2 = trim(right(title2, length(title2) - 5))
where title2 like 'Sent:%';

update tmp_conversation
set title2 = trim(right(title2, length(title2) - 3))
where title2 like 'Re:%';

update tmp_conversation
set title2 = trim(right(title2, length(title2) - 5))
where title2 like 'Sent:%';

update tmp_conversation
set title2 = trim(right(title2, length(title2) - 3))
where title2 like 'Re:%';

update tmp_conversation
set title2 = trim(right(title2, length(title2) - 5))
where title2 like 'Sent:%';

update tmp_conversation
set title2 = trim(right(title2, length(title2) - 3))
where title2 like 'Re:%';

update tmp_conversation
set title2 = trim(right(title2, length(title2) - 5))
where title2 like 'Sent:%';

create table tmp_group (
   title2 varchar(255),
   userids varchar(255),
   groupid int,
   primary key (title2, userids)
);

replace tmp_group (
   title2,
   userids,
   groupid
)
select
   title2,
   userids,
   min(id)
from tmp_conversation
group by title2, userids;

create index tidx_group on tmp_group(title2, userids);
create index tidx_conversation on tmp_conversation(title2, userids);

update tmp_conversation c
join tmp_group g
   on c.title2 = g.title2 and c.userids = g.userids
set c.groupid = g.groupid;
EOT;
      
      $Ex->QueryN($Sql);
      
      // Converations.
      $Conversation_Map = array(
          'groupid' => 'ConversationID',
          'title2' => 'Subject',
          'mt_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
          'mt_from_id' => 'InsertUserID'
          );
      $Sql = "select
   mt.*,
   tc.title2,
   tc.groupid
from ibf_message_topics mt
join tmp_conversation tc
   on mt.mt_id = tc.id";
      $this->ClearFilters('Conversation', $Conversation_Map, $Sql);
      $Ex->ExportTable('Conversation', $Sql, $Conversation_Map);
      
      // Converation Message.
      $ConversationMessage_Map = array(
          'msg_id' => 'MessageID',
          'groupid' => 'ConversationID',
          'msg_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
          'msg_post' => 'Body',
          'Format' => 'Format',
          'msg_author_id' => 'InsertUserID',
          'msg_ip_address' => 'InsertIPAddress'
          );
      $Sql = "select
   tx.*,
   tc.title2,
   tc.groupid,
   'IPB' as Format
from ibf_message_text tx
join ibf_message_topics mt
   on mt.mt_msg_id = tx.msg_id
join tmp_conversation tc
   on mt.mt_id = tc.id";
      $this->ClearFilters('ConversationMessage', $ConversationMessage_Map, $Sql);
      $Ex->ExportTable('ConversationMessage', $Sql, $ConversationMessage_Map);
      
      // User Conversation.
      $UserConversation_Map = array(
          'userid' => 'UserID',
          'groupid' => 'ConversationID'
          );
      $Sql = "select distinct
   g.groupid,
   t.userid
from tmp_to t
join tmp_group g
   on g.groupid = t.id";
      $Ex->ExportTable('UserConversation', $Sql, $UserConversation_Map);
      
      $Ex->QueryN("
      drop table tmp_conversation;
drop table tmp_to;
drop table tmp_to2;
drop table tmp_group;");
   }


   protected function _ExportConversationsV3() {
      $Ex = $this->Ex;
      
      // Converations.
      $Conversation_Map = array(
          'mt_id' => 'ConversationID',
          'mt_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
          'mt_title' => 'Subject',
          'mt_starter_id' => 'InsertUserID'
          );
      $Sql = "select * from ibf_message_topics where mt_is_deleted = 0";
      $this->ClearFilters('Conversation', $Conversation_Map, $Sql);
      $Ex->ExportTable('Conversation', $Sql, $Conversation_Map);
      
      // Converation Message.
      $ConversationMessage_Map = array(
          'msg_id' => 'MessageID',
          'msg_topic_id' => 'ConversationID',
          'msg_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
          'msg_post' => 'Body',
          'Format' => 'Format',
          'msg_author_id' => 'InsertUserID',
          'msg_ip_address' => 'InsertIPAddress'
          );
      $Sql = "select 
            m.*,
            'IPB' as Format
         from ibf_message_posts m";
      $this->ClearFilters('ConversationMessage', $ConversationMessage_Map, $Sql);
      $Ex->ExportTable('ConversationMessage', $Sql, $ConversationMessage_Map);
      
      // User Conversation.
      $UserConversation_Map = array(
          'map_user_id' => 'UserID',
          'map_topic_id' => 'ConversationID',
          'Deleted' => 'Deleted'
          );
      $Sql = "select
         t.*,
         !map_user_active as Deleted
      from ibf_message_topic_user_map t";
      $Ex->ExportTable('UserConversation', $Sql, $UserConversation_Map);
   }
   
   public function ClearFilters($Table, &$Map, &$Sql) {
      $PK = FALSE;
      $Selects = array();
      
      foreach ($Map as $Column => $Info) {
         if (!$PK)
            $PK = $Column;
         
         if (!is_array($Info) || !isset($Info['Filter']))
            continue;
         
         
         $Filter = $Info['Filter'];
         if (isset($Info['SourceColumn']))
            $Source = $Info['SourceColumn'];
         else
            $Source = $Column;

         if (!is_array($Filter)) {
            switch ($Filter) {
               case 'HTMLDecoder':
                  $this->Ex->HTMLDecoderDb($Table, $Column, $PK);
                  unset($Map[$Column]['Filter']);
                  break;
               case 'TimestampToDate':
                  $Selects[] = "from_unixtime($Source) as {$Column}_Date";

                  unset($Map[$Column]);
                  $Map[$Column.'_Date'] = $Info['Column'];
                  break;
            }
         }
      }
      
      if (count($Selects) > 0) {
         $Statement = implode(', ', $Selects);
         $Sql = str_replace('from ', ", $Statement\nfrom ", $Sql);
      }
   }
}
?>