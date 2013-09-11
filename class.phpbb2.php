<?php
/**
 * phpBB exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
 
 $Supported['phpbb2'] = array('name'=>'phpBB 2.*', 'prefix' => 'phpbb_');

class Phpbb2 extends ExportController {

   /** @var array Required tables => columns */
   protected $SourceTables = array(
      'users' => array('user_id', 'username', 'user_password', 'user_email', 'user_timezone', 'user_posts', 'user_regdate', 'user_lastvisit'),
      'groups' => array('group_id', 'group_name', 'group_description'),
      'user_group' => array('user_id', 'group_id'),
      'forums' => array('forum_id', 'forum_name', 'forum_desc', 'forum_order'),
      'topics' => array('topic_id', 'forum_id', 'topic_poster',  'topic_title', 'topic_views', 'topic_first_post_id', 'topic_replies', 'topic_status', 'topic_type', 'topic_time'),
      'posts' => array('post_id', 'topic_id', 'poster_id', 'post_time', 'post_edit_time'),
      'posts_text' => array('post_id', 'post_text'),
      'privmsgs' => array('privmsgs_id', 'privmsgs_subject', 'privmsgs_from_userid', 'privmsgs_to_userid', 'privmsgs_date'),
      'privmsgs_text' => array('privmsgs_text_id', 'privmsgs_bbcode_uid', 'privmsgs_text')
   );

   /**
    * Forum-specific export format.
    * @param ExportModel $Ex
    */
   protected function ForumExport($Ex) {
      // Get the characterset for the comments.
      $CharacterSet = $Ex->GetCharacterSet('posts_text');
      if ($CharacterSet)
         $Ex->CharacterSet = $CharacterSet;
      
      $Ex->SourcePrefix = 'phpbb_';
      
      // Begin
      $Ex->BeginExport('', 'phpBB 2.*', array('HashMethod' => 'phpBB'));

      // Users
      $User_Map = array(
         'user_id'=>'UserID',
         'username'=>'Name',
         'user_password'=>'Password',
         'user_email'=>'Email',
         'user_timezone'=>'HourOffset',
         'user_posts'=>array('Column' => 'CountComments', 'Type' => 'int')
      );
      $Ex->ExportTable('User', "select *,
            FROM_UNIXTIME(nullif(user_regdate, 0)) as DateFirstVisit,
            FROM_UNIXTIME(nullif(user_lastvisit, 0)) as DateLastActive,
            FROM_UNIXTIME(nullif(user_regdate, 0)) as DateInserted
         from :_users", $User_Map);  // ":_" will be replace by database prefix


      // Roles
      $Role_Map = array(
         'group_id'=>'RoleID',
         'group_name'=>'Name',
         'group_description'=>'Description'
      );
      // Skip single-user groups
      $Ex->ExportTable('Role', 'select * from :_groups where group_single_user = 0', $Role_Map);


      // UserRoles
      $UserRole_Map = array(
         'user_id'=>'UserID',
         'group_id'=>'RoleID'
      );
      // Skip pending memberships
      $Ex->ExportTable('UserRole', 'select user_id, group_id from :_users
         union
         select user_id, group_id from :_user_group where user_pending = 0', $UserRole_Map);

      // Categories
      $Category_Map = array(
         'id'=>'CategoryID',
         'cat_title'=>'Name',
         'description'=>'Description',
         'parentid' => 'ParentCategoryID'
      );
      $Ex->ExportTable('Category',
"select
  c.cat_id * 1000 as id,
  c.cat_title,
  c.cat_order * 1000 as Sort,
  null as parentid,
  '' as description
from :_categories c

union all

select
  f.forum_id,
  f.forum_name,
  c.cat_order * 1000 + f.forum_order,
  c.cat_id * 1000 as parentid,
  f.forum_desc
from :_forums f
left join :_categories c
  on f.cat_id = c.cat_id", $Category_Map);

      // Discussions
      $Discussion_Map = array(
         'topic_id'=>'DiscussionID',
         'forum_id'=>'CategoryID',
         'topic_poster'=>'InsertUserID',
         'topic_title'=>'Name',
         'Format'=>'Format',
         'topic_views'=>'CountViews'
      );
      $Ex->ExportTable('Discussion', "select t.*,
        'BBCode' as Format,
         case t.topic_status when 1 then 1 else 0 end as Closed,
         case t.topic_type when 1 then 1 else 0 end as Announce,
         FROM_UNIXTIME(t.topic_time) as DateInserted
        from :_topics t",
        $Discussion_Map);

      // Comments
      $Comment_Map = array(
         'post_id' => 'CommentID',
         'topic_id' => 'DiscussionID',
         'post_text' => array('Column'=>'Body','Filter'=>array($this, 'RemoveBBCodeUIDs')),
         'Format' => 'Format',
         'poster_id' => 'InsertUserID'
      );
      $Ex->ExportTable('Comment', "select p.*, pt.post_text, pt.bbcode_uid,
        'BBCode' as Format,
         FROM_UNIXTIME(p.post_time) as DateInserted,
         FROM_UNIXTIME(nullif(p.post_edit_time,0)) as DateUpdated
         from :_posts p inner join :_posts_text pt on p.post_id = pt.post_id",
         $Comment_Map);

      // Conversations tables.
      $Ex->Query("drop table if exists z_pmto;");

      $Ex->Query("create table z_pmto (
id int unsigned,
userid int unsigned,
primary key(id, userid));");

      $Ex->Query("insert ignore z_pmto (id, userid)
select privmsgs_id, privmsgs_from_userid
from :_privmsgs;");

      $Ex->Query("insert ignore z_pmto (id, userid)
select privmsgs_id, privmsgs_to_userid
from :_privmsgs;");

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
  pm.privmsgs_id,
  pm.privmsgs_subject,
  case when pm.privmsgs_subject like 'Re: %' then trim(substring(pm.privmsgs_subject, 4)) else pm.privmsgs_subject end as subject2,
  t.userids
from :_privmsgs pm
join z_pmto2 t
  on t.id = pm.privmsgs_id;");

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

      // Conversations.
      $Conversation_Map = array(
         'privmsgs_id' => 'ConversationID',
         'privmsgs_from_userid' => 'InsertUserID',
         'RealSubject' => array('Column' => 'Subject', 'Type' => 'varchar(250)', 'Filter' => array('Phpbb2', 'EntityDecode'))
      );

      $Ex->ExportTable('Conversation', "select
  g.subject as RealSubject,
  pm.*,
  from_unixtime(pm.privmsgs_date) as DateInserted
from :_privmsgs pm
join z_pmgroup g
  on g.groupid = pm.privmsgs_id", $Conversation_Map);

      // Coversation Messages.
      $ConversationMessage_Map = array(
          'privmsgs_id' => 'MessageID',
          'group_id' => 'ConversationID',
          'privmsgs_text' => array('Column' => 'Body', 'Filter'=>array($this, 'RemoveBBCodeUIDs')),
          'privmsgs_from_userid' => 'InsertUserID'
      );
      $Ex->ExportTable('ConversationMessage',
      "select
         pm.*,
         txt.*,
         txt.privmsgs_bbcode_uid as bbcode_uid,
         pm2.groupid,
         'BBCode' as Format,
         FROM_UNIXTIME(pm.privmsgs_date) as DateInserted
       from :_privmsgs pm
       join :_privmsgs_text txt
         on pm.privmsgs_id = txt.privmsgs_text_id
       join z_pm pm2
         on pm.privmsgs_id = pm2.id", $ConversationMessage_Map);

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

      // End
      $Ex->EndExport();
   }

   public static function EntityDecode($Value) {
      return html_entity_decode($Value, ENT_QUOTES, 'UTF-8');
   }

   public function RemoveBBCodeUIDs($Value, $Field, $Row) {
      $UID = $Row['bbcode_uid'];
      return str_replace(':'.$UID, '', $Value);
   }
}
?>
