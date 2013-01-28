<?php
/**
 * phpBB exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

class Phpbb3 extends ExportController {

   /** @var array Required tables => columns */
   protected $SourceTables = array(
      'users' => array('user_id', 'username', 'user_password', 'user_email', 'user_timezone', 'user_posts', 'user_regdate', 'user_lastvisit', 'user_regdate'),
      'groups' => array('group_id', 'group_name', 'group_desc'),
      'user_group' => array('user_id', 'group_id'),
      'forums' => array('forum_id', 'forum_name', 'forum_desc', 'left_id', 'parent_id'),
      'topics' => array('topic_id', 'forum_id', 'topic_poster',  'topic_title', 'topic_views', 'topic_first_post_id', 'topic_replies', 'topic_status', 'topic_type', 'topic_time', 'topic_last_post_time', 'topic_last_post_time'),
      'posts' => array('post_id', 'topic_id', 'post_text', 'poster_id', 'post_edit_user', 'post_time', 'post_edit_time'),
      'bookmarks' => array('user_id', 'topic_id')
   );

   /**
    * Forum-specific export format.
    * @param ExportModel $Ex
    */
   protected function ForumExport($Ex) {
      $this->Ex = $Ex;

      // Get the characterset for the comments.
      $CharacterSet = $Ex->GetCharacterSet('posts');
      if ($CharacterSet)
         $Ex->CharacterSet = $CharacterSet;
      
      $Ex->SourcePrefix = 'phpbb_';
      
      
      // Begin
      $Ex->BeginExport('', 'phpBB 3.*', array('HashMethod' => 'phpBB'));

      // Users.
      
      // Grab the avatar salt.
      $Px = $Ex->GetValue("select config_value from phpbb_config where config_name = 'avatar_salt'", '');
      
      $User_Map = array(
         'user_id' => 'UserID',
         'username' => array('Column'=>'Name','Filter'=>array($Ex, 'HTMLDecoder')),
         'user_password' => 'Password',
         'user_email' => 'Email',
         'user_timezone' => 'HourOffset',
         'user_posts' => array('Column' => 'CountComments', 'Type' => 'int'),
         'photo' => 'Photo',
         'user_rank' => 'RankID',
         'user_ip' => 'LastIPAddress'
      );
      $Ex->ExportTable('User', "select *,
            case user_avatar_type 
               when 1 then concat('phpbb/', '$Px', user_avatar) 
               else null end as photo,
            FROM_UNIXTIME(nullif(user_regdate, 0)) as DateFirstVisit,
            FROM_UNIXTIME(nullif(user_lastvisit, 0)) as DateLastActive,
            FROM_UNIXTIME(nullif(user_regdate, 0)) as DateInserted
         from phpbb_users", $User_Map);  // ":_" will be replace by database prefix

      // Roles
      $Role_Map = array(
         'group_id'=>'RoleID',
         'group_name'=>'Name',
         'group_desc'=>'Description'
      );
      $Ex->ExportTable('Role', 'select * from :_groups', $Role_Map);
      
      // Ranks.
      $Rank_Map = array(
         'rank_id' => 'RankID',
         'level' => array('Column' => 'Level', 'Filter' => function($Value) {
            static $Level = 0;
            $Level++;
            
            return $Level;
         }),
         'rank_title' => 'Name',
         'title2' => 'Label',
         'rank_min' => array('Column' => 'Attributes', 'Filter' => function($Value, $Field, $Row) {
            $Result = array();
            
            if ($Row['rank_min']) {
               $Result['Criteria']['CountPosts'] = $Row['rank_min'];
            }
            
            if ($Row['rank_special']) {
               $Result['Criteria']['Manual'] = TRUE;
            }
            
            return serialize($Result);
         })
      );
      $Ex->ExportTable('Rank', "
         select r.*, r.rank_title as title2, 0 as level
         from phpbb_ranks r
         order by rank_special, rank_min;", $Rank_Map);
      
      // Permissions.
      $Ex->ExportTable('Permission', "select
         group_id as RoleID,
         case
            when group_name like '%Guest%' or group_name like 'BOTS' then 'View'
            when group_name like '%Mod%' then 'View,Garden.SignIn.Allow,Garden.Profiles.Edit,Garden.Settings.View,Vanilla.Discussions.Add,Vanilla.Comments.Add,Garden.Moderation.Manage'
            when group_name like '%Admin%' then 'All'
            else 'View,Garden.SignIn.Allow,Garden.Profiles.Edit,Vanilla.Discussions.Add,Vanilla.Comments.Add'
         end as _Permissions
         from phpbb_groups");

      // UserRoles
      $UserRole_Map = array(
         'user_id'=>'UserID',
         'group_id'=>'RoleID'
      );
      $Ex->ExportTable('UserRole', 'select user_id, group_id from :_users
         union
         select user_id, group_id from :_user_group', $UserRole_Map);
      
      
      // Signatutes.
      $UserMeta_Map = array(
         'user_id' => 'UserID',
         'name' => 'Name',
         'user_sig' => array('Column' => 'Value', 'Filter'=>array($this, 'RemoveBBCodeUIDs')));
      $Ex->ExportTable('UserMeta', "
         select user_id, 'Plugin.Signatures.Sig' as name, user_sig, user_sig_bbcode_uid as bbcode_uid
         from phpbb_users
         where length(user_sig) > 1

         union

         select user_id, 'Plugin.Signatures.Format', 'BBCode', null
         from phpbb_users
         where length(user_sig) > 1
         ", $UserMeta_Map);
      

      // Categories
      $Category_Map = array(
         'forum_id'=>'CategoryID',
         'forum_name' => array('Column'=>'Name','Filter'=>array($Ex, 'HTMLDecoder')),
         'forum_desc'=>'Description',
         'left_id'=>'Sort'
      );
      $Ex->ExportTable('Category', "select *,
         nullif(parent_id,0) as ParentCategoryID
         from :_forums", $Category_Map);

      // Discussions
      $Discussion_Map = array(
         'topic_id'=>'DiscussionID',
         'forum_id'=>'CategoryID',
         'topic_poster'=>'InsertUserID',
         'topic_title'=>'Name',
			'Format'=>'Format',
         'topic_views'=>'CountViews',
         'topic_first_post_id'=>array('Column'=>'FirstCommentID','Type'=>'int'),
         'type' => 'Type'
      );
      $Ex->ExportTable('Discussion', "select t.*,
				'BBCode' as Format,
            topic_replies+1 as CountComments,
            case t.topic_status when 1 then 1 else 0 end as Closed,
            case t.topic_type when 1 then 1 else 0 end as Announce,
            case when t.poll_start > 0 then 'poll' else null end as type,
            FROM_UNIXTIME(t.topic_time) as DateInserted,
            FROM_UNIXTIME(t.topic_last_post_time) as DateUpdated,
            FROM_UNIXTIME(t.topic_last_post_time) as DateLastComment
         from :_topics t", $Discussion_Map);

      // Comments
      $Comment_Map = array(
         'post_id' => 'CommentID',
         'topic_id' => 'DiscussionID',
         'post_text' => array('Column'=>'Body','Filter'=>array($this, 'RemoveBBCodeUIDs')),
			'Format' => 'Format',
         'poster_id' => 'InsertUserID',
         'poster_ip' => array('Column' => 'InsertIPAddress', 'Filter' => array($Ex, 'ForceIP4')),
         'post_edit_user' => 'UpdateUserID'
      );
      $Ex->ExportTable('Comment', "select p.*,
				'BBCode' as Format,
            FROM_UNIXTIME(p.post_time) as DateInserted,
            FROM_UNIXTIME(nullif(p.post_edit_time,0)) as DateUpdated
         from :_posts p", $Comment_Map);

      // UserDiscussion
		$UserDiscussion_Map = array(
			'user_id' =>  'UserID',
         'topic_id' => 'DiscussionID');
      $Ex->ExportTable('UserDiscussion', "select b.*,
         1 as Bookmarked
         from :_bookmarks b", $UserDiscussion_Map);

      // Conversations tables.

      $Ex->Query("drop table if exists z_pmto;");

      $Ex->Query("create table z_pmto (
id int unsigned,
userid int unsigned,
primary key(id, userid));");

      $Ex->Query("insert ignore z_pmto (id, userid)
select msg_id, author_id
from :_privmsgs;");

      $Ex->Query("insert ignore z_pmto (id, userid)
select msg_id, user_id
from :_privmsgs_to;");

      $Ex->Query("insert ignore z_pmto (id, userid)
select msg_id, author_id
from :_privmsgs_to;");

      $Ex->Query("drop table if exists z_pmto2;");

      $Ex->Query("create table z_pmto2 (
  id int unsigned,
  userids varchar(250),
  primary key (id)
);");

      $Ex->Query("insert ignore z_pmto2 (id, userids)
select
  id,
  group_concat(userid order by userid)
from z_pmto
group by id;");

      $Ex->Query("drop table if exists z_pm;");

      $Ex->Query("create table z_pm (
  id int unsigned,
  subject varchar(255),
  subject2 varchar(255),
  userids varchar(250),
  groupid int unsigned
);");

      $Ex->Query("insert z_pm (
  id,
  subject,
  subject2,
  userids
)
select
  pm.msg_id,
  pm.message_subject,
  case when pm.message_subject like 'Re: %' then trim(substring(pm.message_subject, 4)) else pm.message_subject end as subject2,
  t.userids
from :_privmsgs pm
join z_pmto2 t
  on t.id = pm.msg_id;");

      $Ex->Query("create index z_idx_pm on z_pm (id);");

      $Ex->Query("drop table if exists z_pmgroup;");

      $Ex->Query("create table z_pmgroup (
  groupid int unsigned,
  subject varchar(255),
  userids varchar(250)
);");

      $Ex->Query("insert z_pmgroup (
  groupid,
  subject,
  userids
)
select
  min(pm.id),
  pm.subject2,
  pm.userids
from z_pm pm
group by pm.subject2, pm.userids;");

      $Ex->Query("create index z_idx_pmgroup on z_pmgroup (subject, userids);");
      $Ex->Query("create index z_idx_pmgroup2 on z_pmgroup (groupid);");

      $Ex->Query("update z_pm pm
join z_pmgroup g
  on pm.subject2 = g.subject and pm.userids = g.userids
set pm.groupid = g.groupid;");
      
      // Polls.
      $Poll_Map = array(
         'poll_id' => 'PollID',
         'poll_title' => 'Name',
         'topic_id' => 'DiscussionID',
         'topic_time' => array('Column' => 'DateInserted', 'Filter' => array($Ex, 'TimestampToDate')),
         'topic_poster' => 'InsertUserID',
         'anonymous' => 'Anonymous');
      $Ex->ExportTable('Poll', "
         select distinct
            t.*,
            t.topic_id as poll_id,
            1 as anonymous
         from phpbb_poll_options po
         join phpbb_topics t
            on po.topic_id = t.topic_id", $Poll_Map);
      
      $PollOption_Map = array(
         'id' => 'PollOptionID',
         'poll_option_id' => 'Sort',
         'topic_id' => 'PollID',
         'poll_option_text' => 'Body',
         'format' => 'Format',
         'poll_option_total' => 'CountVotes',
         'topic_time' => array('Column' => 'DateInserted', 'Filter' => array($Ex, 'TimestampToDate')),
         'topic_poster' => 'InsertUserID'
         );
      $Ex->ExportTable('PollOption', "
         select
            po.*,
            po.poll_option_id * 1000000 + po.topic_id as id,
            'Html' as format,
            t.topic_time,
            t.topic_poster
         from phpbb_poll_options po
         join phpbb_topics t
            on po.topic_id = t.topic_id", $PollOption_Map);
      
      $PollVote_Map = array(
         'vote_user_id' => 'UserID',
         'id' => 'PollOptionID');
      $Ex->ExportTable('PollVote', "
         select v.*, v.poll_option_id * 1000000 + v.topic_id as id
         from phpbb_poll_votes v", $PollVote_Map);

      // Conversations.
      $Conversation_Map = array(
         'msg_id' => 'ConversationID',
         'author_id' => 'InsertUserID',
         'RealSubject' => array('Column' => 'Subject', 'Type' => 'varchar(250)', 'Filter' => array('Phpbb2', 'EntityDecode'))
      );

      $Ex->ExportTable('Conversation', "select
  g.subject as RealSubject,
  pm.*,
  from_unixtime(pm.message_time) as DateInserted
from :_privmsgs pm
join z_pmgroup g
  on g.groupid = pm.msg_id", $Conversation_Map);

      // Coversation Messages.
      $ConversationMessage_Map = array(
          'msg_id' => 'MessageID',
          'groupid' => 'ConversationID',
          'message_text' => array('Column' => 'Body', 'Filter'=>array($this, 'RemoveBBCodeUIDs')),
          'author_id' => 'InsertUserID'
      );
      $Ex->ExportTable('ConversationMessage',
      "select
         pm.*,
         pm2.groupid,
         'BBCode' as Format,
         FROM_UNIXTIME(pm.message_time) as DateInserted
       from :_privmsgs pm
       join z_pm pm2
         on pm.msg_id = pm2.id", $ConversationMessage_Map);

      // User Conversation.
      $UserConversation_Map = array(
         'userid' => 'UserID',
         'groupid' => 'ConversationID'
      );
      $Ex->ExportTable('UserConversation',
      "select
         g.groupid,
         t.userid
       from z_pmto t
       join z_pmgroup g
         on g.groupid = t.id;", $UserConversation_Map);

      $Ex->Query('drop table if exists z_pmto');
      $Ex->Query('drop table if exists z_pmto2;');
      $Ex->Query('drop table if exists z_pm;');
      $Ex->Query('drop table if exists z_pmgroup;');

      // Media.
      $Media_Map = array(
          'attach_id' => 'MediaID',
          'real_filename' => 'Name',
          'post_id' => 'InsertUserID',
          'mimetype' => 'Type',
          'filesize' => 'Size',
      );
      $Ex->ExportTable('Media', 
      "select
  case when a.post_msg_id = t.topic_first_post_id then 'discussion' else 'comment' end as ForeignTable,
  case when a.post_msg_id = t.topic_first_post_id then a.topic_id else a.post_msg_id end as ForeignID,
  concat ('FileUpload/', a.physical_filename) as Path,
  FROM_UNIXTIME(a.filetime) as DateInserted,
  'local' as StorageMethod,
  a.*
from :_attachments a
join :_topics t
  on a.topic_id = t.topic_id", $Media_Map);

      // End
      $Ex->EndExport();
   }

   public function RemoveBBCodeUIDs($r, $Field = '', $Row = '') {
      if (!$r)
         return $r;
      
      $UID = trim($Row['bbcode_uid']);
//      $UID = '2zp03s9s';
      if ($UID)
         $r =  preg_replace("`((?::[a-zA-Z])?:$UID)`", '', $r);

      // Remove smilies.
      $r = preg_replace('#<!\-\- s(.*?) \-\-><img src="\{SMILIES_PATH\}\/.*? \/><!\-\- s\1 \-\->#', '\1', $r);
      // Remove links.
      $regex = '`<!-- [a-z] --><a\s+class="[^"]+"\s+href="([^"]+)">([^<]+)</a><!-- [a-z] -->`';
      $r = preg_replace($regex, '[url=$1]$2[/url]', $r);

      $r = str_replace(array('&quot;', '&#39;', '&#58;', 'Â', '&#46;', '&amp;'), array('"', "'", ':', '', '.', '&'), $r);
      return $r;
   }
}
?>