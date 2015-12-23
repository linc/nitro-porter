<?php
/* Written by John Crenshaw for Priacta, Inc. */
/**
 * SMF exporter tool
 *
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author John Crenshaw
 * @package VanillaPorter
 */

$Supported['smf'] = array('name' => 'Simple Machines 1', 'prefix' => 'smf_'); // SMF
$Supported['smf']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'Attachments' => 1,
    'PrivateMessages' => 1,
    'Passwords' => 1,
);

class SMF extends ExportController {

    /** @var array Required tables => columns */
    protected $SourceTables = array(
        'boards' => array(),
        'messages' => array(),
        'personal_messages' => array(),
        'pm_recipients' => array(),
        'categories' => array('ID_CAT', 'name', 'catOrder'),
        'membergroups' => array(),
        'members' => array('ID_MEMBER', 'memberName', 'passwd', 'emailAddress', 'dateRegistered')
    );

    /**
     * Forum-specific export format.
     * @param ExportModel $Ex
     */
    protected function forumExport($Ex) {

        $CharacterSet = $Ex->getCharacterSet('messages');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        // Begin
        $Ex->beginExport('', 'SMF 1.*', array('HashMethod' => 'Django'));

        // Users
        $User_Map = array(
            'ID_MEMBER' => 'UserID',
            'memberName' => 'Name',
            'password' => 'Password',
            'emailAddress' => 'Email',
            'DateInserted' => 'DateInserted',
            'timeOffset' => 'HourOffset',
            'posts' => 'CountComments',
            //'avatar'=>'Photo',
            'birthdate' => 'DateOfBirth',
            'DateFirstVisit' => 'DateFirstVisit',
            'DateLastActive' => 'DateLastActive',
            'DateUpdated' => 'DateUpdated'
        );
        $Ex->exportTable('User', "
         select m.*,
            from_unixtime(dateRegistered) as DateInserted,
            from_unixtime(dateRegistered) as DateFirstVisit,
            from_unixtime(lastLogin) as DateLastActive,
            from_unixtime(lastLogin) as DateUpdated,
            concat('sha1$', lower(memberName), '$', passwd) as `password`,
            if(m.avatar <> '', m.avatar, concat('attachments/', a.filename)) as Photo
         from :_members m
         left join :_attachments a on a.ID_MEMBER = m.ID_MEMBER ", $User_Map);

        // Roles
        $Role_Map = array(
            'ID_GROUP' => 'RoleID',
            'groupName' => 'Name'
        );
        $Ex->exportTable('Role', "select * from :_membergroups", $Role_Map);

        // UserRoles
        $UserRole_Map = array(
            'ID_MEMBER' => 'UserID',
            'ID_GROUP' => 'RoleID'
        );
        $Ex->exportTable('UserRole', "select * from :_members", $UserRole_Map);

        // Categories
        $Category_Map = array(
            'Name' => array('Column' => 'Name', 'Filter' => array($this, 'DecodeNumericEntity'))
        );

        $Ex->exportTable('Category',
            "
      select
        (`ID_CAT` + 1000000) as `CategoryID`,
        `name` as `Name`,
      '' as `Description`,
      null as `ParentCategoryID`,
        `catOrder` as `Sort`
      from :_categories

     union

      select
        b.`ID_BOARD` as `CategoryID`,

        b.`name` as `Name`,
        b.`description` as `Description`,
      (CASE WHEN b.`ID_PARENT` = 0 THEN (`ID_CAT` + 1000000) ELSE `ID_PARENT` END) as `ParentCategoryID`,
        b.`boardOrder` as `Sort`
      from :_boards b

     ", $Category_Map);

        // Discussions
        $Discussion_Map = array(
            'ID_TOPIC' => 'DiscussionID',
            'subject' => array('Column' => 'Name', 'Filter' => array($this, 'DecodeNumericEntity')),
            //,'Filter'=>'bb2html'),
            'body' => array('Column' => 'Body'),
            //,'Filter'=>'bb2html'),
            'Format' => 'Format',
            'ID_BOARD' => 'CategoryID',
            'DateInserted' => 'DateInserted',
            'DateUpdated' => 'DateUpdated',
            'ID_MEMBER' => 'InsertUserID',
            'DateLastComment' => 'DateLastComment',
            'UpdateUserID' => 'UpdateUserID',
            'locked' => 'Closed',
            'isSticky' => 'Announce',
            'CountComments' => 'CountComments',
            'numViews' => 'CountViews',
            'LastCommentUserID' => 'LastCommentUserID',
            'ID_LAST_MSG' => 'LastCommentID'
        );
        $Ex->exportTable('Discussion', "
      select t.*,
         (t.numReplies + 1) as CountComments,
         m.subject,
         m.body,
         from_unixtime(m.posterTime) as DateInserted,
         from_unixtime(m.modifiedTime) as DateUpdated,
         m.ID_MEMBER,
         from_unixtime(m_end.posterTime) AS DateLastComment,
         m_end.ID_MEMBER AS UpdateUserID,
         m_end.ID_MEMBER AS LastCommentUserID,
         'BBCode' as Format
       from :_topics t
       join :_messages as m on t.ID_FIRST_MSG = m.ID_MSG
       join :_messages as m_end on t.ID_LAST_MSG = m_end.ID_MSG

       -- where t.spam = 0 AND m.spam = 0;

       ", $Discussion_Map);

        // Comments
        $Comment_Map = array(
            'ID_MSG' => 'CommentID',
            'ID_TOPIC' => 'DiscussionID',
            'Format' => 'Format',
            'body' => array('Column' => 'Body'), //,'Filter'=>'bb2html'),
            'ID_MEMBER' => 'InsertUserID',
            'DateInserted' => 'DateInserted'
        );
        $Ex->exportTable('Comment',
            "select m.*,
         from_unixtime(m.posterTime) AS DateInserted,
         'BBCode' as Format
       from :_messages m
       join :_topics t on m.ID_TOPIC = t.ID_TOPIC
       where m.ID_MSG <> t.ID_FIRST_MSG;
       ", $Comment_Map);

        // Media
        $Media_Map = array(
            'ID_ATTACH' => 'MediaID',
            'ID_MSG' => 'ForeignID',
            'size' => 'Size',
            'height' => 'ImageHeight',
            'width' => 'ImageWidth',
            'extract_mimetype' => array(
                'Column' => 'Type',
                'Filter' => function($value, $field, $row) {
                    return $this->getMimeTypeFromFileName($row['Path']);
                }
            ),
            'thumb_path' => array('Column' => 'ThumbPath', 'Filter' => array($this, 'FilterThumbnailData')),
            'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'FilterThumbnailData')),
        );
        $Ex->exportTable('Media', "
            select
                a.*,
                concat('attachments/', a.filename) as Path,
                IF(b.filename is not null, concat('attachments/', b.filename), null) as thumb_path,
                null as extract_mimetype,
                b.width as thumb_width,
                if(t.ID_TOPIC is null, 'Comment', 'Discussion') as ForeignTable
            from :_attachments a
                left join :_attachments b on b.ID_ATTACH = a.ID_THUMB
                left join :_topics t on a.ID_MSG = t.ID_FIRST_MSG
            where a.attachmentType = 0
                and a.ID_MSG > 0
        ", $Media_Map);

        // Conversations need a bit more conversion so execute a series of queries for that.
        $Ex->query('create table :_smfpmto (
  id int,
  to_id int,
  deleted tinyint,
  primary key(id, to_id)
)');

        $Ex->query('insert :_smfpmto (
  id,
  to_id,
  deleted
)
select
  ID_PM,
  ID_MEMBER_FROM,
  deletedBySender
from :_personal_messages');

        $Ex->query('insert ignore :_smfpmto (
  id,
  to_id,
  deleted
)
select
  ID_PM,
  ID_MEMBER,
  deleted
from :_pm_recipients');

        $Ex->query('create table :_smfpmto2 (
  id int,
  to_ids varchar(255),
  primary key(id)
)');

        $Ex->query('insert :_smfpmto2 (
  id,
  to_ids
)
select
  id,
  group_concat(to_id order by to_id)
from :_smfpmto
group by id');

        $Ex->query('create table :_smfpm (
  id int,
  group_id int,
  subject varchar(200),
  subject2 varchar(200),
  from_id int,
  to_ids varchar(255))');

        $Ex->query('create index :_idx_smfpm2 on :_smfpm (subject2, from_id)');
        $Ex->query('create index :_idx_smfpmg on :_smfpm (group_id)');

        $Ex->query('insert :_smfpm (
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
  ID_MEMBER_FROM,
  to2.to_ids
from :_personal_messages pm
join :_smfpmto2 to2
  on pm.ID_PM = to2.id');

        $Ex->query('create table :_smfgroups (
  id int primary key,
  subject2 varchar(200),
  to_ids varchar(255)
)');

        $Ex->query('insert :_smfgroups
select
  min(id) as group_id, subject2, to_ids
from :_smfpm
group by subject2, to_ids');

        $Ex->query('create index :_idx_smfgroups on :_smfgroups (subject2, to_ids)');

        $Ex->query('update :_smfpm pm
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
        $Ex->exportTable('Conversation',
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
            'body' => array('Column' => 'Body')
        );
        $Ex->exportTable('ConversationMessage',
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
        $Ex->exportTable('UserConversation',

            "select
   pm.group_id,
   t.to_id,
   t.deleted
 from :_smfpmto t
 join :_smfpm pm
   on t.id = pm.group_id", $UserConv_Map);

        $Ex->query('drop table :_smfpm');
        $Ex->query('drop table :_smfpmto');
        $Ex->query('drop table :_smfpmto2');
        $Ex->query('drop table :_smfgroups');

        // End
        $Ex->endExport();
//      echo implode("\n\n", $Ex->Queries);
    }

    public function decodeNumericEntity($Text) {
        if (function_exists('mb_decode_numericentity')) {
            $convmap = array(0x0, 0x2FFFF, 0, 0xFFFF);

            return mb_decode_numericentity($Text, $convmap, 'UTF-8');
        } else {
            return $Text;
        }
    }

    public function _pcreEntityToUtf($matches) {
        $char = intval(is_array($matches) ? $matches[1] : $matches);

        if ($char < 0x80) {
            // to prevent insertion of control characters
            if ($char >= 0x20) {
                return htmlspecialchars(chr($char));
            } else {
                return "&#$char;";
            }
        } else {
            if ($char < 0x80000) {
                return chr(0xc0 | (0x1f & ($char >> 6))) . chr(0x80 | (0x3f & $char));
            } else {
                return chr(0xe0 | (0x0f & ($char >> 12))) . chr(0x80 | (0x3f & ($char >> 6))) . chr(0x80 | (0x3f & $char));
            }
        }
    }

    /**
     * Determine mime type from file name
     *
     * @param string $fileName File name (Can be full path or file name only)
     * @return null|string Mime type if it could be determined or null.
     */
    public function getMimeTypeFromFileName($fileName) {
        $mimeType = null;

        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        if ($extension) {
            $mimeType = MimeTypeFromExtension('.'.strtolower($extension));
        }

        return $mimeType;
    }

    /**
     * Filter used by $Media_Map to replace value for ThumbPath and ThumbWidth when the file is not an image.
     *
     * @access public
     * @see ExportModel::_ExportTable
     *
     * @param string $value Current value
     * @param string $field Current field
     * @param array $row Contents of the current record.
     * @return string|null Return the supplied value if the record's file is an image. Return null otherwise
     */
    public function FilterThumbnailData($value, $field, $row) {
        $mimeType = $this->getMimeTypeFromFileName($row['Path']);
        if ($mimeType && strpos($mimeType, 'image/') === 0) {
            return $value;
        } else {
            return null;
        }
    }
}

// Closing PHP tag required. (make.php)
?>
