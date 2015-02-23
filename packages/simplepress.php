<?php
/**
 * ppPress exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['SimplePress'] = array('name' => 'SimplePress 1', 'prefix' => 'wp_');
$Supported['SimplePress']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'PrivateMessages' => 1,
    'Permissions' => 1,
    'Passwords' => 1,
);

class SimplePress extends ExportController
{

    /** @var array Required tables => columns */
    protected $SourceTables = array(
        'sfforums' => array(),
        'sfposts' => array(),
        'sftopics' => array(),
        'users' => array('ID', 'user_nicename', 'user_pass', 'user_email', 'user_registered')
        //'meta' => array()
    );

    /**
     * Forum-specific export format.
     * @param ExportModel $Ex
     */
    protected function ForumExport($Ex) {
        $Ex->SourcePrefix = 'wp_';

        // Get the characterset for the comments.
        $CharacterSet = $Ex->GetCharacterSet('posts');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        // Begin
        $Ex->BeginExport('', 'SimplePress 1.*', array('HashMethod' => 'Vanilla'));

        // Users
        $User_Map = array(
            'user_id' => 'UserID',
            'display_name' => 'Name',
            'user_pass' => 'Password',
            'user_email' => 'Email',
            'user_registered' => 'DateInserted',
            'lastvisit' => 'DateLastActive'
        );
        $Ex->ExportTable('User',
            "select m.*, u.user_pass, u.user_email, u.user_registered
          from wp_users u
          join wp_sfmembers m
            on u.ID = m.user_id;", $User_Map);

        // Roles
        $Role_Map = array(
            'usergroup_id' => 'RoleID',
            'usergroup_name' => 'Name',
            'usergroup_desc' => 'Description'
        );
        $Ex->ExportTable('Role',
            "select
            usergroup_id,
            usergroup_name,
            usergroup_desc
         from wp_sfusergroups

         union

         select
            100,
            'Administrators',
            ''", $Role_Map);

        // Permissions.
        $Ex->ExportTable('Permission', "select
            usergroup_id as RoleID,
case
   when usergroup_name like 'Guest%' then 'View'
   when usergroup_name like 'Member%' then 'View,Garden.SignIn.Allow,Garden.Profiles.Edit,Vanilla.Discussions.Add,Vanilla.Comments.Add'
   when usergroup_name like 'Mod%' then 'View,Garden.SignIn.Allow,Garden.Profiles.Edit,Garden.Settings.View,Vanilla.Discussions.Add,Vanilla.Comments.Add,Garden.Moderation.Manage'
end as _Permissions
         from wp_sfusergroups

         union

         select 100, 'All'");

        // UserRoles
        $UserRole_Map = array(
            'user_id' => 'UserID',
            'usergroup_id' => 'RoleID'
        );
        $Ex->ExportTable('UserRole',
            "select
            m.user_id,
            m.usergroup_id
         from wp_sfmemberships m

         union

         select
            um.user_id,
            100
         from wp_usermeta um
         where um.meta_key = 'wp_capabilities'
            and um.meta_value like '%PF Manage Forums%'", $UserRole_Map);

        // Categories
        $Category_Map = array(
            'forum_id' => 'CategoryID',
            'forum_name' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'forum_desc' => 'Description',
            'forum_seq' => 'Sort',
            'form_slug' => 'UrlCode',
            'parent_id' => 'ParentCategoryID'
        );
        $Ex->ExportTable('Category', "
         select
            f.forum_id,
            f.forum_name,
            f.forum_seq,
            f.forum_desc,
            lower(f.forum_slug) as forum_slug,
            case when f.parent = 0 then f.group_id + 1000 else f.parent end as parent_id
         from wp_sfforums f

         union

         select
            1000 + g.group_id,
            g.group_name,
            g.group_seq,
            g.group_desc,
            null,
            null
         from wp_sfgroups g", $Category_Map);

        // Discussions
        $Discussion_Map = array(
            'topic_id' => 'DiscussionID',
            'forum_id' => 'CategoryID',
            'user_id' => 'InsertUserID',
            'topic_name' => 'Name',
            'Format' => 'Format',
            'topic_date' => 'DateInserted',
            'topic_pinned' => 'Announce',
            'topic_slug' => array('Column' => 'Slug', 'Type' => 'varchar(200)')
        );
        $Ex->ExportTable('Discussion', "select t.*,
            'Html' as Format
         from :_sftopics t", $Discussion_Map);

        if ($Ex->Exists('sftags')) {
            // Tags
            $Tag_Map = array(
                'tag_id' => 'TagID',
                'tag_name' => 'Name'
            );
            $Ex->ExportTable('Tag', "select * from :_sftags", $Tag_Map);

            if ($Ex->Exists('sftagmeta')) {
                $TagDiscussion_Map = array(
                    'tag_id' => 'TagID',
                    'topic_id' => 'DiscussionID'
                );
                $Ex->ExportTable('TagDiscussion', "select * from :_sftagmeta", $TagDiscussion_Map);
            }
        }

        // Comments
        $Comment_Map = array(
            'post_id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'post_content' => 'Body',
            'Format' => 'Format',
            'user_id' => 'InsertUserID',
            'post_date' => 'DateInserted',
            'poster_ip' => 'InsertIPAddress'
        );
        $Ex->ExportTable('Comment', "select p.*,
            'Html' as Format
         from :_sfposts p", $Comment_Map);

        // Conversation.
        $Conv_Map = array(
            'message_id' => 'ConversationID',
            'from_id' => 'InsertUserID',
            'sent_date' => 'DateInserted'
        );
        $Ex->ExportTable('Conversation',
            "select *
         from :_sfmessages
         where is_reply = 0", $Conv_Map);

        // ConversationMessage.
        $ConvMessage_Map = array(
            'message_id' => 'MessageID',
            'from_id' => 'InsertUserID',
            'message' => array('Column' => 'Body')
        );
        $Ex->ExportTable('ConversationMessage',
            'select c.message_id as ConversationID, m.*
         from :_sfmessages c
         join :_sfmessages m
           on (m.is_reply = 0 and m.message_id = c.message_id) or (m.is_reply = 1 and c.is_reply = 0 and m.message_slug = c.message_slug and m.from_id in (c.from_id, c.to_id) and m.to_id in (c.from_id, c.to_id));',
            $ConvMessage_Map);

        // UserConversation
        $UserConv_Map = array(
            'message_id' => 'ConversationID',
            'from_id' => 'UserID'
        );
        $Ex->ExportTable('UserConversation',
            'select message_id, from_id
         from :_sfmessages
         where is_reply = 0

         union

         select message_id, to_id
         from :_sfmessages
         where is_reply = 0',
            $UserConv_Map);

        // End
        $Ex->EndExport();
    }
}

?>
