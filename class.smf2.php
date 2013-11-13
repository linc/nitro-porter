<?php
/* Written by John Crenshaw for Priacta, Inc. */

/**
 * SMF exporter tool
 *
 * @copyright Priacta, Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
 
$Supported['SMF2'] = array('name'=>'SMF (Simple Machines) 2.*', 'prefix' => 'smf_');

class SMF2 extends ExportController {

   /** @var array Required tables => columns */
   protected $SourceTables = array(
      'boards' => array(),
      'messages' => array(),
      'personal_messages' => array(),
      'pm_recipients' => array(),
      'categories' => array('id_cat', 'name', 'cat_order'),
      'membergroups' => array(),
      'members' => array('id_member', 'member_name', 'passwd', 'email_address', 'date_registered')
   );
   
   /**
    * Forum-specific export format.
    * @param ExportModel $Ex
    */
   protected function ForumExport($Ex) {
      // Begin
      $Ex->BeginExport('', 'SMF 2.*', array('HashMethod' => 'Django'));

      // Users
      $User_Map = array(
         'id_member'=>'UserID',
         'member_name'=>'Name',
         'password'=>'Password',
         'email_address'=>'Email',
         'DateInserted'=>'DateInserted',
         'timeOffset'=>'HourOffset',
         'posts'=>'CountComments',
         //'avatar'=>'Photo',
          'Photo' => 'Photo',
         'birthdate'=>'DateOfBirth',
         'DateFirstVisit'=>'DateFirstVisit',
         'DateLastActive'=>'DateLastActive',
         'DateUpdated'=>'DateUpdated'
      );
      $Ex->ExportTable('User', "
         select m.*,
            from_unixtime(date_registered) as DateInserted,
            from_unixtime(date_registered) as DateFirstVisit,
            from_unixtime(last_login) as DateLastActive,
            from_unixtime(last_login) as DateUpdated,
            concat('sha1$', lower(member_name), '$', passwd) as `password`,
            if(m.avatar <> '', m.avatar, concat('attachments/', a.filename)) as Photo
         from :_members m
         left join :_attachments a on a.id_member = m.id_member ", $User_Map);

      // Roles
      $Role_Map = array(
         'id_group'=>'RoleID',
         'group_name'=>'Name'
      );
      $Ex->ExportTable('Role', "select * from :_membergroups", $Role_Map);

      // UserRoles
      $UserRole_Map = array(
         'id_member'=>'UserID',
		 'id_group'=>'RoleID'
      );
      $Ex->ExportTable('UserRole', "select * from :_members", $UserRole_Map);

      // Categories
      $Category_Map = array(
          'Name' => array('Column' => 'Name', 'Filter' => array($this, 'DecodeNumericEntity'))
      );

      $Ex->ExportTable('Category',
	  "
      select
        (`id_cat` + 1000000) as `CategoryID`,
        `name` as `Name`,
		'' as `Description`,
		null as `ParentCategoryID`,
        `cat_order` as `Sort`
      from :_categories

	  union

      select
        b.`id_board` as `CategoryID`,

        b.`name` as `Name`,
		  b.`description` as `Description`,
		(CASE WHEN b.`id_parent` = 0 THEN (`id_cat` + 1000000) ELSE `id_parent` END) as `ParentCategoryID`,
        b.`board_order` as `Sort`
      from :_boards b

	  ", $Category_Map);

      // Discussions
      $Discussion_Map = array(
         'id_topic' => 'DiscussionID',
         'subject' => array('Column'=>'Name', 'Filter' => array($this, 'DecodeNumericEntity')), //,'Filter'=>'bb2html'),
         'body' => array('Column'=>'Body'), //,'Filter'=>'bb2html'),
         'Format'=>'Format',
         'id_board'=> 'CategoryID',
         'DateInserted'=>'DateInserted',
         'DateUpdated'=>'DateUpdated',
         'id_member'=>'InsertUserID',
         'DateLastComment'=>'DateLastComment',
         'UpdateUserID'=>'UpdateUserID',
         'locked'=>'Closed',
         'isSticky'=>'Announce',
         'CountComments'=>'CountComments',
         'numViews'=>'CountViews',
         'LastCommentUserID'=>'LastCommentUserID',
         'id_last_msg'=>'LastCommentID'
      );
      $Ex->ExportTable('Discussion', "
      select t.*,
         (t.num_replies + 1) as CountComments,
         m.subject,
         m.body,
         from_unixtime(m.poster_time) as DateInserted,
         from_unixtime(m.modified_time) as DateUpdated,
         m.id_member,
         from_unixtime(m_end.poster_time) AS DateLastComment,
         m_end.id_member AS UpdateUserID,
         m_end.id_member AS LastCommentUserID,
         'BBCode' as Format
       from :_topics t
       join :_messages as m on t.id_first_msg = m.id_msg
       join :_messages as m_end on t.id_last_msg = m_end.id_msg

		 -- where t.spam = 0 AND m.spam = 0;

		 ", $Discussion_Map);

      // Comments
      $Comment_Map = array(
         'id_msg' => 'CommentID',
         'id_topic' => 'DiscussionID',
         'Format' => 'Format',
         'body' => array('Column'=>'Body'), //,'Filter'=>'bb2html'),
         'id_member' => 'InsertUserID',
         'DateInserted' => 'DateInserted'
      );
      $Ex->ExportTable('Comment', 
      "select m.*,
         from_unixtime(m.poster_time) AS DateInserted,
         'BBCode' as Format
       from :_messages m
		 join :_topics t on m.id_topic = t.id_topic
		 where m.id_msg <> t.id_first_msg;
       ", $Comment_Map);
       
       // Media
       $Media_Map = array(
         'ID_ATTACH' => 'MediaID',
         'id_msg' => 'ForeignID',
         'size' => 'Size',
         'height' => 'ImageHeight',
         'width' => 'ImageWidth'
      );
      $Ex->ExportTable('Media', 
      "select a.*,
         concat('attachments/', a.filename) as Path,
         concat('attachments/', b.filename) as ThumbPath,
         if(t.id_topic is null, 'Comment', 'Discussion') as ForeignTable
       from :_attachments a
       left join :_attachments b on b.ID_ATTACH = a.ID_THUMB
       left join :_topics t on a.id_msg = t.id_first_msg
       where a.attachment_type = 0
         and a.id_msg > 0;", $Media_Map);

    // Conversations need a bit more conversion so execute a series of queries for that.
    $Ex->Query('create table :_smfpmto (
  id int,
  to_id int,
  deleted tinyint,
  primary key(id, to_id)
)');

    $Ex->Query('insert :_smfpmto (
  id,
  to_id,
  deleted
)
select
  ID_PM,
  id_member_FROM,
  deleted_by_sender
from :_personal_messages');

    $Ex->Query('insert ignore :_smfpmto (
  id,
  to_id,
  deleted
)
select
  ID_PM,
  id_member,
  deleted
from :_pm_recipients');

    $Ex->Query('create table :_smfpmto2 (
  id int,
  to_ids varchar(255),
  primary key(id)
)');

    $Ex->Query('insert :_smfpmto2 (
  id,
  to_ids
)
select
  id,
  group_concat(to_id order by to_id)
from :_smfpmto
group by id');

    $Ex->Query('create table :_smfpm (
  id int,
  group_id int,
  subject varchar(200),
  subject2 varchar(200),
  from_id int,
  to_ids varchar(255))');

    $Ex->Query('create index :_idx_smfpm2 on :_smfpm (subject2, from_id)');
    $Ex->Query('create index :_idx_smfpmg on :_smfpm (group_id)');

    $Ex->Query('insert :_smfpm (
  id,
  subject,
  subject2,
  from_id,
  to_ids
)
select
  ID_PM,
  subject,
  case when subject like \'Re: %\' then trim(substring(subject, 4)) else subject end as subject2,
  id_member_FROM,
  to2.to_ids
from :_personal_messages pm
join :_smfpmto2 to2
  on pm.ID_PM = to2.id');

    $Ex->Query('create table :_smfgroups (
  id int primary key,
  subject2 varchar(200),
  to_ids varchar(255)
)');

    $Ex->Query('insert :_smfgroups
select
  min(id) as group_id, subject2, to_ids
from :_smfpm
group by subject2, to_ids');

    $Ex->Query('create index :_idx_smfgroups on :_smfgroups (subject2, to_ids)');

    $Ex->Query('update :_smfpm pm
join :_smfgroups g
  on pm.subject2 = g.subject2 and pm.to_ids = g.to_ids
set pm.group_id = g.id');

	 // Conversation.
	 $Conv_Map = array(
		'id' => 'ConversationID',
		'from_id' => 'InsertUserID',
		'DateInserted' => 'DateInserted',
      'subject2' => array('Column' => 'Subject', 'Type' => 'varchar(255)')
	 );
	 $Ex->ExportTable('Conversation',
"select
  pm.group_id,
  pm.from_id,
  pm.subject2,
  from_unixtime(pm2.msgtime) as DateInserted
from :_smfpm pm
join :_personal_messages pm2
  on pm.id = pm2.ID_PM
where pm.id = pm.group_id", $Conv_Map);

	 // ConversationMessage.
	 $ConvMessage_Map = array(
		'id' => 'MessageID',
		'group_id' => 'ConversationID',
		'DateInserted' => 'DateInserted',
		'from_id' => 'InsertUserID',
		'body' => array('Column'=>'Body')
	 );
	 $Ex->ExportTable('ConversationMessage',
"select
  pm.id,
  pm.group_id,
  from_unixtime(pm2.msgtime) as DateInserted,
  pm.from_id,
  'BBCode' as Format,
  case when pm.subject = pm.subject2 then concat(pm.subject, '\n\n', pm2.body) else pm2.body end as body
from :_smfpm pm
join :_personal_messages pm2
  on pm.id = pm2.ID_PM", $ConvMessage_Map);

	 // UserConversation.
	 $UserConv_Map = array(
		'to_id' => 'UserID',
		'group_id' => 'ConversationID',
      'deleted' => 'Deleted'
	 );
	 $Ex->ExportTable('UserConversation',

"select
   pm.group_id,
   t.to_id,
   t.deleted
 from :_smfpmto t
 join :_smfpm pm
   on t.id = pm.group_id", $UserConv_Map);
    
      $Ex->Query('drop table :_smfpm');
      $Ex->Query('drop table :_smfpmto');
      $Ex->Query('drop table :_smfpmto2');
      $Ex->Query('drop table :_smfgroups');

      // End
      $Ex->EndExport();
//      echo implode("\n\n", $Ex->Queries);
   }

   function DecodeNumericEntity($Text) {
      if (function_exists('mb_decode_numericentity')) {
         $convmap = array(0x0, 0x2FFFF, 0, 0xFFFF);
         return mb_decode_numericentity($Text, $convmap, 'UTF-8');
      } else {
         return $Text;
      }
   }

   function _pcreEntityToUtf($matches) {
     $char = intval(is_array($matches) ? $matches[1] : $matches);

     if ($char < 0x80) {
         // to prevent insertion of control characters
         if ($char >= 0x20)
            return htmlspecialchars(chr($char));
         else
            return "&#$char;";
     } else if ($char < 0x80000) {
         return chr(0xc0 | (0x1f & ($char >> 6))) . chr(0x80 | (0x3f & $char));
     } else {
         return chr(0xe0 | (0x0f & ($char >> 12))) . chr(0x80 | (0x3f & ($char >> 6))). chr(0x80 | (0x3f & $char));
     }
   }
}
?>
