<?php
/**
 * Expression Engine exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010-2015
 * @license GNU GPL2
 * @package VanillaPorter
 */

$Supported['expressionengine'] = array('name' => 'Expression Engine Discussion Forum', 'prefix' => 'forum_');
$Supported['expressionengine']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Bookmarks' => 1,
    'Passwords' => 1,
    'Signatures' => 1,
    'Permissions' => 1,
    'Attachments' => 1,
);

class ExpressionEngine extends ExportController {
    /**
     *
     * @param ExportModel $Ex
     */
    public function ForumExport($Ex) {

        $CharacterSet = $Ex->GetCharacterSet('topics');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        $Ex->BeginExport('', 'Expression Engine');
        $Ex->SourcePrefix = 'forum_';

        $this->ExportConversations();


        // Permissions.
        $Permission_Map = array(
            'group_id' => 'RoleID',
            'can_access_cp' => 'Garden.Settings.View',
            'can_access_edit' => 'Vanilla.Discussions.Edit',
            'can_edit_all_comments' => 'Vanilla.Comments.Edit',
            'can_access_admin' => 'Garden.Settings.Manage',
            'can_admin_members' => 'Garden.Users.Edit',
            'can_moderate_comments' => 'Garden.Moderation.Manage',
            'can_view_profiles' => 'Garden.Profiles.View',
            'can_post_comments' => 'Vanilla.Comments.Add',
            'can_view_online_system' => 'Vanilla.Discussions.View',
            'can_sign_in' => 'Garden.SignIn.Allow',
            'can_view_profiles3' => 'Garden.Activity.View',
            'can_post_comments2' => 'Vanilla.Discussions.Add'
        );
        $Permission_Map = $Ex->FixPermissionColumns($Permission_Map);
        foreach ($Permission_Map as $Column => &$Info) {
            if (is_array($Info) && isset($Info['Column'])) {
                $Info['Filter'] = array($this, 'YNBool');
            }
        }

        $Ex->ExportTable('Permission', "
         SELECT
            g.can_view_profiles AS can_view_profiles2,
            g.can_view_profiles AS can_view_profiles3,
            g.can_post_comments AS can_post_comments2,
            g.can_post_comments AS can_sign_in,
            CASE WHEN can_access_admin = 'y' THEN 'all' WHEN can_view_online_system = 'y' THEN 'view' END AS _Permissions,
            g.*
         FROM forum_member_groups g
      ", $Permission_Map);


        // User.
        $User_Map = array(
            'member_id' => 'UserID',
            'username' => array('Column' => 'Username', 'Type' => 'varchar(50)'),
            'screen_name' => array('Column' => 'Name', 'Filter' => array($Ex, 'HTMLDecoder')),
            'Password2' => 'Password',
            'email' => 'Email',
            'ipaddress' => 'InsertIPAddress',
            'join_date' => array('Column' => 'DateInserted', 'Filter' => array($Ex, 'TimestampToDate')),
            'last_activity' => array('Column' => 'DateLastActive', 'Filter' => array($Ex, 'TimestampToDate')),
            'timezone' => 'HourOffset',
            'location' => 'Location'
        );
        $Ex->ExportTable('User', "
         SELECT
            'django' AS HashMethod,
            concat('sha1$$', password) AS Password2,
            CASE WHEN bday_y > 1900 THEN concat(bday_y, '-', bday_m, '-', bday_d) ELSE NULL END AS DateOfBirth,
            from_unixtime(join_date) AS DateFirstVisit,
            ip_address AS LastIPAddress,
            CASE WHEN avatar_filename = '' THEN NULL ELSE concat('imported/', avatar_filename) END AS Photo,
            u.*
         FROM forum_members u", $User_Map);


        // Role.
        $Role_Map = array(
            'group_id' => 'RoleID',
            'group_title' => 'Name',
            'group_description' => 'Description'
        );
        $Ex->ExportTable('Role', "
         SELECT *
         FROM forum_member_groups", $Role_Map);


        // User Role.
        $UserRole_Map = array(
            'member_id' => 'UserID',
            'group_id' => 'RoleID'
        );
        $Ex->ExportTable('UserRole', "
         SELECT *
         FROM forum_members u", $UserRole_Map);


        // UserMeta
        $Ex->ExportTable('UserMeta', "
         SELECT
            member_id AS UserID,
            'Plugin.Signatures.Sig' AS Name,
            signature AS Value
         FROM forum_members
         WHERE signature <> ''");


        // Category.
        $Category_Map = array(
            'forum_id' => 'CategoryID',
            'forum_name' => 'Name',
            'forum_description' => 'Description',
            'forum_parent' => 'ParentCategoryID',
            'forum_order' => 'Sort'
        );
        $Ex->ExportTable('Category', "
         SELECT * FROM forum_forums", $Category_Map);


        // Discussion.
        $Discussion_Map = array(
            'topic_id' => 'DiscussionID',
            'forum_id' => 'CategoryID',
            'author_id' => 'InsertUserID',
            'title' => array('Column' => 'Name', 'Filter' => array($Ex, 'HTMLDecoder')),
            'ip_address' => 'InsertIPAddress',
            'body' => array('Column' => 'Body', 'Filter' => array($this, 'CleanBodyBrackets')),
            'body2' => array('Column' => 'Format', 'Filter' => array($this, 'GuessFormat')),
            'topic_date' => array('Column' => 'DateInserted', 'Filter' => array($Ex, 'TimestampToDate')),
            'topic_edit_date' => array('Column' => 'DateUpdated', 'Filter' => array($Ex, 'TimestampToDate')),
            'topic_edit_author' => 'UpdateUserID'
        );
        $Ex->ExportTable('Discussion', "
          SELECT
             CASE WHEN announcement = 'y' THEN 1 WHEN sticky = 'y' THEN 2 ELSE 0 END AS Announce,
             CASE WHEN status = 'c' THEN 1 ELSE 0 END AS Closed,
             t.body AS body2,
             t.*
          FROM forum_forum_topics t", $Discussion_Map);


        // Comment.
        $Comment_Map = array(
            'post_id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'author_id' => 'InsertUserID',
            'ip_address' => 'InsertIPAddress',
            'body' => array('Column' => 'Body', 'Filter' => array($this, 'CleanBodyBrackets')),
            'body2' => array('Column' => 'Format', 'Filter' => array($this, 'GuessFormat')),
            'post_date' => array('Column' => 'DateInserted', 'Filter' => array($Ex, 'TimestampToDate')),
            'post_edit_date' => array('Column' => 'DateUpdated', 'Filter' => array($Ex, 'TimestampToDate')),
            'post_edit_author' => 'UpdateUserID'
        );
        $Ex->ExportTable('Comment', "
      SELECT
         'Html' AS Format,
         p.body AS body2,
         p.*
      FROM forum_forum_posts p", $Comment_Map);


        // Media.
        $Media_Map = array(
            'filename' => 'Name',
            'extension' => array('Column' => 'Type', 'Filter' => 'MimeTypeFromExtension'),
            'thumb_path' => array('Column' => 'ThumbPath', 'Filter' => array($this, 'FilterThumbnailData')),
            'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'FilterThumbnailData')),
            'filesize' => 'Size',
            'member_id' => 'InsertUserID',
            'attachment_date' => array('Column' => 'DateInserted', 'Filter' => array($Ex, 'TimestampToDate')),
            'filehash' => array('Column' => 'FileHash', 'Type' => 'varchar(100)')
        );
        $Ex->ExportTable('Media', "
         SELECT
            concat('imported/', filename) AS Path,
            concat('imported/', filename) as thumb_path,
            128 as thumb_width,
            CASE WHEN post_id > 0 THEN post_id ELSE topic_id END AS ForeignID,
            CASE WHEN post_id > 0 THEN 'comment' ELSE 'discussion' END AS ForeignTable,
            a.*
         FROM forum_forum_attachments a", $Media_Map);

        $Ex->EndExport();
    }

    /**
     * Private message conversion.
     */
    public function ExportConversations() {
        $Ex = $this->Ex;

        $this->_ExportConversationTemps();

        // Conversation.
        $Conversation_Map = array(
            'message_id' => 'ConversationID',
            'title2' => array('Column' => 'Subject', 'Type' => 'varchar(255)'),
            'sender_id' => 'InsertUserID',
            'message_date' => array('Column' => 'DateInserted', 'Filter' => array($Ex, 'TimestampToDate')),
        );
        $Ex->ExportTable('Conversation', "
         SELECT
         pm.*,
         g.title AS title2
       FROM forum_message_data pm
       JOIN z_pmgroup g
         ON g.group_id = pm.message_id;", $Conversation_Map);

        // User Conversation.
        $UserConversation_Map = array(
            'group_id' => 'ConversationID',
            'userid' => 'UserID'
        );
        $Ex->ExportTable('UserConversation', "
         SELECT
         g.group_id,
         t.userid
       FROM z_pmto t
       JOIN z_pmgroup g
         ON g.group_id = t.message_id;", $UserConversation_Map);

        // Conversation Message.
        $Message_Map = array(
            'group_id' => 'ConversationID',
            'message_id' => 'MessageID',
            'message_body' => 'Body',
            'message_date' => array('Column' => 'DateInserted', 'Filter' => array($Ex, 'TimestampToDate')),
            'sender_id' => 'InsertUserID'
        );
        $Ex->ExportTable('ConversationMessage', "
         SELECT
            pm.*,
            pm2.group_id,
            'BBCode' AS Format
          FROM forum_message_data pm
          JOIN z_pmtext pm2
            ON pm.message_id = pm2.message_id", $Message_Map);
    }

    /**
     * Create temporary tables for private message conversion.
     */
    public function _ExportConversationTemps() {
        $Ex = $this->Ex;

        $Ex->Query('DROP TABLE IF EXISTS z_pmto;');
        $Ex->Query('CREATE TABLE z_pmto (
            message_id INT UNSIGNED,
            userid INT UNSIGNED,
            deleted TINYINT(1),
            PRIMARY KEY(message_id, userid)
            );');

        $Ex->Query("insert ignore z_pmto (
                message_id,
                userid,
                deleted
            )
            select
                message_id,
                recipient_id,
                case when message_deleted = 'y' then 1 else 0 end as `deleted`
            from forum_message_copies;");

        $Ex->Query("UPDATE forum_message_data
            SET message_recipients = replace(message_recipients, '|', ',');");

        $Ex->Query("UPDATE forum_message_data
            SET message_cc = replace(message_cc, '|', ',');");

        $Ex->Query('insert ignore z_pmto (
            message_id,
            userid
          )
          select
            message_id,
            sender_id
          from forum_message_data;');

        $Ex->Query("insert ignore z_pmto (
                message_id,
                userid
            )
            select
                message_id,
                u.member_id
            from forum_message_data m
            join forum_members u
                on  FIND_IN_SET(u.member_id, m.message_cc) > 0
            where m.message_cc <> '';");

        $Ex->Query("insert ignore z_pmto (
                message_id,
                userid
            )
            select
                message_id,
                u.member_id
            from forum_message_data m
            join forum_members u
                on  FIND_IN_SET(u.member_id, m.message_cc) > 0
            where m.message_cc <> '';");

        $Ex->Query("DROP TABLE IF EXISTS z_pmto2;");

        $Ex->Query("CREATE TABLE z_pmto2 (
            message_id INT UNSIGNED,
            userids VARCHAR(250),
            PRIMARY KEY (message_id)
            );");

        $Ex->Query("insert z_pmto2 (
            message_id,
            userids
            )
            select
                message_id,
                group_concat(userid order by userid)
            from z_pmto t
            group by t.message_id;");

        $Ex->Query("DROP TABLE IF EXISTS z_pmtext;");
        $Ex->Query("CREATE TABLE z_pmtext (
            message_id INT UNSIGNED,
            title VARCHAR(250),
            title2 VARCHAR(250),
            userids VARCHAR(250),
            group_id INT UNSIGNED
            );");

        $Ex->Query("insert z_pmtext (
            message_id,
            title,
            title2
            )
            select
                message_id,
                message_subject,
                case when message_subject like 'Re: %' then trim(substring(message_subject, 4)) else message_subject end as title2
            from forum_message_data;");

        $Ex->Query("CREATE INDEX z_idx_pmtext ON z_pmtext (message_id);");

        $Ex->Query("UPDATE z_pmtext pm
            JOIN z_pmto2 t
                ON pm.message_id = t.message_id
            SET pm.userids = t.userids;");

        $Ex->Query("DROP TABLE IF EXISTS z_pmgroup;");
        $Ex->Query("CREATE TABLE z_pmgroup (
            group_id INT UNSIGNED,
            title VARCHAR(250),
            userids VARCHAR(250)
            );");

        $Ex->Query("insert z_pmgroup (
            group_id,
            title,
            userids
            )
            select
                min(pm.message_id),
                pm.title2,
                t2.userids
            from z_pmtext pm
            join z_pmto2 t2
                on pm.message_id = t2.message_id
            group by pm.title2, t2.userids;");

        $Ex->Query("CREATE INDEX z_idx_pmgroup ON z_pmgroup (title, userids);");
        $Ex->Query("CREATE INDEX z_idx_pmgroup2 ON z_pmgroup (group_id);");

        $Ex->Query("UPDATE z_pmtext pm
            JOIN z_pmgroup g
                ON pm.title2 = g.title AND pm.userids = g.userids
            SET pm.group_id = g.group_id;");
    }

    /**
     * Filter used by $Media_Map to replace value for ThumbPath and ThumbWidth when the file is not an image.
     *
     * @access public
     * @see ExportModel::_ExportTable
     *
     * @param string $Value Current value
     * @param string $Field Current field
     * @param array $Row Contents of the current record.
     * @return string|null Return the supplied value if the record's file is an image. Return null otherwise
     */
    public function FilterThumbnailData($Value, $Field, $Row) {
        if (strpos(MimeTypeFromExtension(strtolower($Row['extension'])), 'image/') === 0) {
            return $Value;
        } else {
            return null;
        }
    }

}
