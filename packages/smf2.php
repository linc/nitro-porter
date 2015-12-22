<?php
/* Written by John Crenshaw for Priacta, Inc. */

/**
 * SMF exporter tool
 *
 * @copyright Priacta, Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['smf2'] = array('name' => 'Simple Machines 2', 'prefix' => 'smf_');
$Supported['smf2']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Attachments' => 1,
    'Bookmarks' => 1,
    'PrivateMessages' => 1,
    'Passwords' => 1,
);

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
    protected function forumExport($Ex) {

        $CharacterSet = $Ex->getCharacterSet('messages');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        // Begin
        $Ex->beginExport('', 'SMF 2.*', array('HashMethod' => 'Django'));

        // Users
        $User_Map = array(
            'id_member' => 'UserID',
            'member_name' => 'Name',
            'password' => 'Password',
            'email_address' => 'Email',
            'DateInserted' => 'DateInserted',
            'timeOffset' => 'HourOffset',
            'posts' => 'CountComments',
            //'avatar'=>'Photo',
            'Photo' => 'Photo',
            'birthdate' => 'DateOfBirth',
            'DateFirstVisit' => 'DateFirstVisit',
            'DateLastActive' => 'DateLastActive',
            'DateUpdated' => 'DateUpdated'
        );
        $Ex->exportTable('User', "
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
            'id_group' => 'RoleID',
            'group_name' => 'Name'
        );
        $Ex->exportTable('Role', "select * from :_membergroups", $Role_Map);

        // UserRoles
        $UserRole_Map = array(
            'id_member' => 'UserID',
            'id_group' => 'RoleID'
        );
        $Ex->exportTable('UserRole', "select * from :_members", $UserRole_Map);

        // Categories
        $Category_Map = array(
            'Name' => array('Column' => 'Name', 'Filter' => array($this, 'DecodeNumericEntity'))
        );

        $Ex->exportTable('Category',
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
            'subject' => array('Column' => 'Name', 'Filter' => array($this, 'DecodeNumericEntity')),
            //,'Filter'=>'bb2html'),
            'body' => array('Column' => 'Body'),
            //,'Filter'=>'bb2html'),
            'Format' => 'Format',
            'id_board' => 'CategoryID',
            'DateInserted' => 'DateInserted',
            'DateUpdated' => 'DateUpdated',
            'id_member' => 'InsertUserID',
            'DateLastComment' => 'DateLastComment',
            'UpdateUserID' => 'UpdateUserID',
            'locked' => 'Closed',
            'isSticky' => 'Announce',
            'CountComments' => 'CountComments',
            'numViews' => 'CountViews',
            'LastCommentUserID' => 'LastCommentUserID',
            'id_last_msg' => 'LastCommentID'
        );
        $Ex->exportTable('Discussion', "
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
            'body' => array('Column' => 'Body'), //,'Filter'=>'bb2html'),
            'id_member' => 'InsertUserID',
            'DateInserted' => 'DateInserted'
        );
        $Ex->exportTable('Comment',
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
        $Ex->exportTable('Media',
            "select a.*,
               concat('attachments/', a.filename) as Path,
               concat('attachments/', b.filename) as ThumbPath,
               if(t.id_topic is null, 'Comment', 'Discussion') as ForeignTable
             from :_attachments a
             left join :_attachments b on b.ID_ATTACH = a.ID_THUMB
             left join :_topics t on a.id_msg = t.id_first_msg
             where a.attachment_type = 0
               and a.id_msg > 0;", $Media_Map);


        // Conversations
        $Conversation_Map = array(
            'id_pm_head' => 'ConversationID',
            'subject' => 'Subject',
            'id_member_from' => 'InsertUserID',
            'unixmsgtime' => 'DateInserted',
        );

        $Ex->exportTable('Conversation',
            "select
              pm.*,
              from_unixtime(pm.msgtime) as unixmsgtime
            from :_personal_messages pm
            ", $Conversation_Map);


        $ConvMsg_Map = array(
            'id_pm' => 'MessageID',
            'id_pm_head' => 'ConversationID',
            'body' => 'Body',
            'format' => 'Format',
            'id_member_from' => 'InsertUserID',
            'unixmsgtime' => 'DateInserted',
        );

        $Ex->exportTable('ConversationMessage',
            "select
              pm.*,
              from_unixtime(pm.msgtime) as unixmsgtime ,
              'BBCode' as format
            from :_personal_messages pm
            ", $ConvMsg_Map);


        $UserConv_Map = array(
            'id_member2' => 'UserId',
            'id_pm_head' => 'ConversationID',
            'deleted2' => 'Deleted'
        );

        $Ex->exportTable('UserConversation',
            "(select
              pm.id_member_from as id_member2,
              pm.id_pm_head,
              pm.deleted_by_sender as deleted2
            from :_personal_messages pm )
            UNION ALL
            (select
            pmr.id_member as id_member2,
            pm.id_pm_head,
            pmr.deleted as deleted2
            from :_personal_messages pm join :_pm_recipients pmr on pmr.id_pm = pm.id_pm
            )
            ", $UserConv_Map);


        // End

        $Ex->endExport();

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
}

?>
