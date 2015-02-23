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

class ExpressionEngine extends ExportController
{
    /**
     *
     * @param ExportModel $Ex
     */
    public function ForumExport($Ex) {

        // Get the characterset for the comments.
        $CharacterSet = $Ex->GetCharacterSet('forum_topics');
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
         select
            g.can_view_profiles as can_view_profiles2,
            g.can_view_profiles as can_view_profiles3,
            g.can_post_comments as can_post_comments2,
            g.can_post_comments as can_sign_in,
            case when can_access_admin = 'y' then 'all' when can_view_online_system = 'y' then 'view' end as _Permissions,
            g.*
         from forum_member_groups g
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
         select
            'django' as HashMethod,
            concat('sha1$$', password) as Password2,
            case when bday_y > 1900 then concat(bday_y, '-', bday_m, '-', bday_d) else null end as DateOfBirth,
            from_unixtime(join_date) as DateFirstVisit,
            ip_address as LastIPAddress,
            case when avatar_filename = '' then null else concat('imported/', avatar_filename) end as Photo,
            u.*
         from forum_members u", $User_Map);


        // Role.
        $Role_Map = array(
            'group_id' => 'RoleID',
            'group_title' => 'Name',
            'group_description' => 'Description'
        );
        $Ex->ExportTable('Role', "
         select *
         from forum_member_groups", $Role_Map);


        // User Role.
        $UserRole_Map = array(
            'member_id' => 'UserID',
            'group_id' => 'RoleID'
        );
        $Ex->ExportTable('UserRole', "
         select *
         from forum_members u", $UserRole_Map);


        // UserMeta
        $Ex->ExportTable('UserMeta', "
         select
            member_id as UserID,
            'Plugin.Signatures.Sig' as Name,
            signature as Value
         from forum_members
         where signature <> ''");


        // Category.
        $Category_Map = array(
            'forum_id' => 'CategoryID',
            'forum_name' => 'Name',
            'forum_description' => 'Description',
            'forum_parent' => 'ParentCategoryID',
            'forum_order' => 'Sort'
        );
        $Ex->ExportTable('Category', "
         select * from forum_forums", $Category_Map);


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
          select
             case when announcement = 'y' then 1 when sticky = 'y' then 2 else 0 end as Announce,
             case when status = 'c' then 1 else 0 end as Closed,
             t.body as body2,
             t.*
          from forum_forum_topics t", $Discussion_Map);


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
      select
         'Html' as Format,
         p.body as body2,
         p.*
      from forum_forum_posts p", $Comment_Map);


        // Media.
        $Media_Map = array(
            'filename' => 'Name',
            'extension' => array('Column' => 'Type', 'Filter' => array($this, 'MimeTypeFromExtension')),
            'filesize' => 'Size',
            'member_id' => 'InsertUserID',
            'attachment_date' => array('Column' => 'DateInserted', 'Filter' => array($Ex, 'TimestampToDate')),
            'filehash' => array('Column' => 'FileHash', 'Type' => 'varchar(100)')
        );
        $Ex->ExportTable('Media', "
         select
            concat('imported/', filename) as Path,
            case when post_id > 0 then post_id else topic_id end as ForeignID,
            case when post_id > 0 then 'comment' else 'discussion' end as ForeignTable,
            'local' as StorageMethod,
            a.*
         from forum_forum_attachments a", $Media_Map);

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
         select
         pm.*,
         g.title as title2
       from forum_message_data pm
       join z_pmgroup g
         on g.group_id = pm.message_id;", $Conversation_Map);

        // User Conversation.
        $UserConversation_Map = array(
            'group_id' => 'ConversationID',
            'userid' => 'UserID'
        );
        $Ex->ExportTable('UserConversation', "
         select
         g.group_id,
         t.userid
       from z_pmto t
       join z_pmgroup g
         on g.group_id = t.message_id;", $UserConversation_Map);

        // Conversation Message.
        $Message_Map = array(
            'group_id' => 'ConversationID',
            'message_id' => 'MessageID',
            'message_body' => 'Body',
            'message_date' => array('Column' => 'DateInserted', 'Filter' => array($Ex, 'TimestampToDate')),
            'sender_id' => 'InsertUserID'
        );
        $Ex->ExportTable('ConversationMessage', "
         select
            pm.*,
            pm2.group_id,
            'BBCode' as Format
          from forum_message_data pm
          join z_pmtext pm2
            on pm.message_id = pm2.message_id", $Message_Map);
    }

    /**
     * Create temporary tables for private message conversion.
     */
    public function _ExportConversationTemps() {
        $Ex = $this->Ex;

        $Ex->Query('drop table if exists z_pmto;');
        $Ex->Query('create table z_pmto (
            message_id int unsigned,
            userid int unsigned,
            deleted tinyint(1),
            primary key(message_id, userid)
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

        $Ex->Query("update forum_message_data
            set message_recipients = replace(message_recipients, '|', ',');");

        $Ex->Query("update forum_message_data
            set message_cc = replace(message_cc, '|', ',');");

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

        $Ex->Query("drop table if exists z_pmto2;");

        $Ex->Query("create table z_pmto2 (
            message_id int unsigned,
            userids varchar(250),
            primary key (message_id)
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

        $Ex->Query("drop table if exists z_pmtext;");
        $Ex->Query("create table z_pmtext (
            message_id int unsigned,
            title varchar(250),
            title2 varchar(250),
            userids varchar(250),
            group_id int unsigned
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

        $Ex->Query("create index z_idx_pmtext on z_pmtext (message_id);");

        $Ex->Query("update z_pmtext pm
            join z_pmto2 t
                on pm.message_id = t.message_id
            set pm.userids = t.userids;");

        $Ex->Query("drop table if exists z_pmgroup;");
        $Ex->Query("create table z_pmgroup (
            group_id int unsigned,
            title varchar(250),
            userids varchar(250)
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

        $Ex->Query("create index z_idx_pmgroup on z_pmgroup (title, userids);");
        $Ex->Query("create index z_idx_pmgroup2 on z_pmgroup (group_id);");

        $Ex->Query("update z_pmtext pm
            join z_pmgroup g
                on pm.title2 = g.title and pm.userids = g.userids
            set pm.group_id = g.group_id;");
    }

}

?>
