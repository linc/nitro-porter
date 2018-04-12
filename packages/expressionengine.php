<?php
/**
 * Expression Engine exporter tool
 *
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['expressionengine'] = array('name' => 'Expression Engine Discussion Forum', 'prefix' => 'forum_');
$supported['expressionengine']['features'] = array(
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
     * @param ExportModel $ex
     */
    public function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('topics');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->beginExport('', 'Expression Engine');
        $ex->sourcePrefix = 'forum_';

        $this->exportConversations();


        // Permissions.
        $permission_Map = array(
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
        $permission_Map = $ex->fixPermissionColumns($permission_Map);
        foreach ($permission_Map as $column => &$info) {
            if (is_array($info) && isset($info['Column'])) {
                $info['Filter'] = array($this, 'YNBool');
            }
        }

        $ex->exportTable('Permission', "
         SELECT
            g.can_view_profiles AS can_view_profiles2,
            g.can_view_profiles AS can_view_profiles3,
            g.can_post_comments AS can_post_comments2,
            g.can_post_comments AS can_sign_in,
            CASE WHEN can_access_admin = 'y' THEN 'all' WHEN can_view_online_system = 'y' THEN 'view' END AS _Permissions,
            g.*
         FROM forum_member_groups g
      ", $permission_Map);


        // User.
        $user_Map = array(
            'member_id' => 'UserID',
            'username' => array('Column' => 'Username', 'Type' => 'varchar(50)'),
            'screen_name' => array('Column' => 'Name', 'Filter' => array($ex, 'HTMLDecoder')),
            'Password2' => 'Password',
            'email' => 'Email',
            'ipaddress' => 'InsertIPAddress',
            'join_date' => array('Column' => 'DateInserted', 'Filter' => array($ex, 'timestampToDate')),
            'last_activity' => array('Column' => 'DateLastActive', 'Filter' => array($ex, 'timestampToDate')),
            'timezone' => 'HourOffset',
            'location' => 'Location'
        );
        $ex->exportTable('User', "
         SELECT
            'django' AS HashMethod,
            concat('sha1$$', password) AS Password2,
            CASE WHEN bday_y > 1900 THEN concat(bday_y, '-', bday_m, '-', bday_d) ELSE NULL END AS DateOfBirth,
            from_unixtime(join_date) AS DateFirstVisit,
            ip_address AS LastIPAddress,
            CASE WHEN avatar_filename = '' THEN NULL ELSE concat('imported/', avatar_filename) END AS Photo,
            u.*
         FROM forum_members u", $user_Map);


        // Role.
        $role_Map = array(
            'group_id' => 'RoleID',
            'group_title' => 'Name',
            'group_description' => 'Description'
        );
        $ex->exportTable('Role', "
         SELECT *
         FROM forum_member_groups", $role_Map);


        // User Role.
        $userRole_Map = array(
            'member_id' => 'UserID',
            'group_id' => 'RoleID'
        );
        $ex->exportTable('UserRole', "
         SELECT *
         FROM forum_members u", $userRole_Map);


        // UserMeta
        $ex->exportTable('UserMeta', "
         SELECT
            member_id AS UserID,
            'Plugin.Signatures.Sig' AS Name,
            signature AS Value
         FROM forum_members
         WHERE signature <> ''");


        // Category.
        $category_Map = array(
            'forum_id' => 'CategoryID',
            'forum_name' => 'Name',
            'forum_description' => 'Description',
            'forum_parent' => 'ParentCategoryID',
            'forum_order' => 'Sort'
        );
        $ex->exportTable('Category', "
         SELECT * FROM forum_forums", $category_Map);


        // Discussion.
        $discussion_Map = array(
            'topic_id' => 'DiscussionID',
            'forum_id' => 'CategoryID',
            'author_id' => 'InsertUserID',
            'title' => array('Column' => 'Name', 'Filter' => array($ex, 'HTMLDecoder')),
            'ip_address' => 'InsertIPAddress',
            'body' => array('Column' => 'Body', 'Filter' => array($this, 'cleanBodyBrackets')),
            'body2' => array('Column' => 'Format', 'Filter' => array($this, 'guessFormat')),
            'topic_date' => array('Column' => 'DateInserted', 'Filter' => array($ex, 'timestampToDate')),
            'topic_edit_date' => array('Column' => 'DateUpdated', 'Filter' => array($ex, 'timestampToDate')),
            'topic_edit_author' => 'UpdateUserID'
        );
        $ex->exportTable('Discussion', "
          SELECT
             CASE WHEN announcement = 'y' THEN 1 WHEN sticky = 'y' THEN 2 ELSE 0 END AS Announce,
             CASE WHEN status = 'c' THEN 1 ELSE 0 END AS Closed,
             t.body AS body2,
             t.*
          FROM forum_forum_topics t", $discussion_Map);


        // Comment.
        $comment_Map = array(
            'post_id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'author_id' => 'InsertUserID',
            'ip_address' => 'InsertIPAddress',
            'body' => array('Column' => 'Body', 'Filter' => array($this, 'cleanBodyBrackets')),
            'body2' => array('Column' => 'Format', 'Filter' => array($this, 'guessFormat')),
            'post_date' => array('Column' => 'DateInserted', 'Filter' => array($ex, 'timestampToDate')),
            'post_edit_date' => array('Column' => 'DateUpdated', 'Filter' => array($ex, 'timestampToDate')),
            'post_edit_author' => 'UpdateUserID'
        );
        $ex->exportTable('Comment', "
      SELECT
         'Html' AS Format,
         p.body AS body2,
         p.*
      FROM forum_forum_posts p", $comment_Map);


        // Media.
        $media_Map = array(
            'filename' => 'Name',
            'extension' => array('Column' => 'Type', 'Filter' => 'mimeTypeFromExtension'),
            'thumb_path' => array('Column' => 'ThumbPath', 'Filter' => array($this, 'filterThumbnailData')),
            'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'filterThumbnailData')),
            'filesize' => 'Size',
            'member_id' => 'InsertUserID',
            'attachment_date' => array('Column' => 'DateInserted', 'Filter' => array($ex, 'timestampToDate')),
            'filehash' => array('Column' => 'FileHash', 'Type' => 'varchar(100)')
        );
        $ex->exportTable('Media', "
         SELECT
            concat('imported/', filename) AS Path,
            concat('imported/', filename) as thumb_path,
            128 as thumb_width,
            CASE WHEN post_id > 0 THEN post_id ELSE topic_id END AS ForeignID,
            CASE WHEN post_id > 0 THEN 'comment' ELSE 'discussion' END AS ForeignTable,
            a.*
         FROM forum_forum_attachments a", $media_Map);

        $ex->endExport();
    }

    /**
     * Private message conversion.
     */
    public function exportConversations() {
        $ex = $this->ex;

        $this->_exportConversationTemps();

        // Conversation.
        $conversation_Map = array(
            'message_id' => 'ConversationID',
            'title2' => array('Column' => 'Subject', 'Type' => 'varchar(255)'),
            'sender_id' => 'InsertUserID',
            'message_date' => array('Column' => 'DateInserted', 'Filter' => array($ex, 'timestampToDate')),
        );
        $ex->exportTable('Conversation', "
         SELECT
         pm.*,
         g.title AS title2
       FROM forum_message_data pm
       JOIN z_pmgroup g
         ON g.group_id = pm.message_id;", $conversation_Map);

        // User Conversation.
        $userConversation_Map = array(
            'group_id' => 'ConversationID',
            'userid' => 'UserID'
        );
        $ex->exportTable('UserConversation', "
         SELECT
         g.group_id,
         t.userid
       FROM z_pmto t
       JOIN z_pmgroup g
         ON g.group_id = t.message_id;", $userConversation_Map);

        // Conversation Message.
        $message_Map = array(
            'group_id' => 'ConversationID',
            'message_id' => 'MessageID',
            'message_body' => 'Body',
            'message_date' => array('Column' => 'DateInserted', 'Filter' => array($ex, 'timestampToDate')),
            'sender_id' => 'InsertUserID'
        );
        $ex->exportTable('ConversationMessage', "
         SELECT
            pm.*,
            pm2.group_id,
            'BBCode' AS Format
          FROM forum_message_data pm
          JOIN z_pmtext pm2
            ON pm.message_id = pm2.message_id", $message_Map);
    }

    /**
     * Create temporary tables for private message conversion.
     */
    public function _exportConversationTemps() {
        $ex = $this->ex;

        $ex->query('DROP TABLE IF EXISTS z_pmto;');
        $ex->query('CREATE TABLE z_pmto (
            message_id INT UNSIGNED,
            userid INT UNSIGNED,
            deleted TINYINT(1),
            PRIMARY KEY(message_id, userid)
            );');

        $ex->query("insert ignore z_pmto (
                message_id,
                userid,
                deleted
            )
            select
                message_id,
                recipient_id,
                case when message_deleted = 'y' then 1 else 0 end as `deleted`
            from forum_message_copies;");

        $ex->query("UPDATE forum_message_data
            SET message_recipients = replace(message_recipients, '|', ',');");

        $ex->query("UPDATE forum_message_data
            SET message_cc = replace(message_cc, '|', ',');");

        $ex->query('insert ignore z_pmto (
            message_id,
            userid
          )
          select
            message_id,
            sender_id
          from forum_message_data;');

        $ex->query("insert ignore z_pmto (
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

        $ex->query("insert ignore z_pmto (
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

        $ex->query("DROP TABLE IF EXISTS z_pmto2;");

        $ex->query("CREATE TABLE z_pmto2 (
            message_id INT UNSIGNED,
            userids VARCHAR(250),
            PRIMARY KEY (message_id)
            );");

        $ex->query("insert z_pmto2 (
            message_id,
            userids
            )
            select
                message_id,
                group_concat(userid order by userid)
            from z_pmto t
            group by t.message_id;");

        $ex->query("DROP TABLE IF EXISTS z_pmtext;");
        $ex->query("CREATE TABLE z_pmtext (
            message_id INT UNSIGNED,
            title VARCHAR(250),
            title2 VARCHAR(250),
            userids VARCHAR(250),
            group_id INT UNSIGNED
            );");

        $ex->query("insert z_pmtext (
            message_id,
            title,
            title2
            )
            select
                message_id,
                message_subject,
                case when message_subject like 'Re: %' then trim(substring(message_subject, 4)) else message_subject end as title2
            from forum_message_data;");

        $ex->query("CREATE INDEX z_idx_pmtext ON z_pmtext (message_id);");

        $ex->query("UPDATE z_pmtext pm
            JOIN z_pmto2 t
                ON pm.message_id = t.message_id
            SET pm.userids = t.userids;");

        $ex->query("DROP TABLE IF EXISTS z_pmgroup;");
        $ex->query("CREATE TABLE z_pmgroup (
            group_id INT UNSIGNED,
            title VARCHAR(250),
            userids VARCHAR(250)
            );");

        $ex->query("insert z_pmgroup (
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

        $ex->query("CREATE INDEX z_idx_pmgroup ON z_pmgroup (title, userids);");
        $ex->query("CREATE INDEX z_idx_pmgroup2 ON z_pmgroup (group_id);");

        $ex->query("UPDATE z_pmtext pm
            JOIN z_pmgroup g
                ON pm.title2 = g.title AND pm.userids = g.userids
            SET pm.group_id = g.group_id;");
    }

    /**
     * Filter used by $Media_Map to replace value for ThumbPath and ThumbWidth when the file is not an image.
     *
     * @access public
     * @see ExportModel::_exportTable
     *
     * @param string $value Current value
     * @param string $field Current field
     * @param array $row Contents of the current record.
     * @return string|null Return the supplied value if the record's file is an image. Return null otherwise
     */
    public function filterThumbnailData($value, $field, $row) {
        if (strpos(mimeTypeFromExtension(strtolower($row['extension'])), 'image/') === 0) {
            return $value;
        } else {
            return null;
        }
    }

}

// Closing PHP tag required. (make.php)
?>
