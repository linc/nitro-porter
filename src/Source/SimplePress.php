<?php

/**
 * Simple:Press exporter tool
 *
 * @author  Todd Burry
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class SimplePress extends Source
{
    public const SUPPORTED = [
        'name' => 'SimplePress 1',
        'prefix' => 'wp_',
        'charset_table' => 'posts',
        'hashmethod' => 'Vanilla',
        'options' => [
        ],
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 0,
            'PrivateMessages' => 1,
            'Signatures' => 0,
            'Attachments' => 0,
            'Bookmarks' => 0,
            'Permissions' => 1,
        ]
    ];

    /**
     * @var array Required tables => columns
     */
    public $sourceTables = array(
        'sfforums' => array(),
        'sfposts' => array(),
        'sftopics' => array(),
        'users' => array('ID', 'user_nicename', 'user_pass', 'user_email', 'user_registered'),
    );

    /**
     * Forum-specific export format.
     *
     * @param ExportModel $ex
     */
    public function run($ex)
    {
        $this->users($ex);
        $this->roles($ex);
        //$this->permissions($ex);

        $this->categories($ex);
        $this->discussions($ex);
        $this->tags($ex);
        $this->comments($ex);
        $this->conversations($ex);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $user_Map = array(
            'user_id' => 'UserID',
            'display_name' => 'Name',
            'user_pass' => 'Password',
            'user_email' => 'Email',
            'user_registered' => 'DateInserted',
            'lastvisit' => 'DateLastActive'
        );
        $ex->export(
            'User',
            "select m.*, u.user_pass, u.user_email, u.user_registered
                from :_users u
                join :_sfmembers m
                    on u.ID = m.user_id;",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $role_Map = array(
            'usergroup_id' => 'RoleID',
            'usergroup_name' => 'Name',
            'usergroup_desc' => 'Description'
        );
        $ex->export(
            'Role',
            "select
                usergroup_id,
                usergroup_name,
                usergroup_desc
            from :_sfusergroups
             union
             select
                100,
                'Administrators',
                ''",
            $role_Map
        );

        // UserRoles
        $userRole_Map = array(
            'user_id' => 'UserID',
            'usergroup_id' => 'RoleID'
        );
        $ex->export(
            'UserRole',
            "select
                    m.user_id,
                    m.usergroup_id
                from :_sfmemberships m
                union
                select
                    um.user_id,
                    100
                from :_usermeta um
                where um.meta_key = 'wp_capabilities'
                    and um.meta_value like '%PF Manage Forums%'",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function permissions(ExportModel $ex): void
    {
        $member = 'View,Garden.SignIn.Allow,Garden.Profiles.Edit,Vanilla.Discussions.Add,Vanilla.Comments.Add';
        $ex->export(
            'Permission',
            "select usergroup_id as RoleID,
                    case
                       when usergroup_name like 'Guest%' then 'View'
                       when usergroup_name like 'Member%'
                            then $member
                       when usergroup_name like 'Mod%'
                            then concat('View,Garden.SignIn.Allow,Garden.Profiles.Edit,Garden.Settings.View,',
                                'Vanilla.Discussions.Add,Vanilla.Comments.Add,Garden.Moderation.Manage')
                    end as _Permissions
                             from :_sfusergroups
                union
                select 100, 'All'"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array(
            'forum_id' => 'CategoryID',
            'forum_name' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'forum_desc' => 'Description',
            'forum_seq' => 'Sort',
            'form_slug' => 'UrlCode',
            'parent_id' => 'ParentCategoryID'
        );
        $ex->export(
            'Category',
            "select
                    f.forum_id,
                    f.forum_name,
                    f.forum_seq,
                    f.forum_desc,
                    lower(f.forum_slug) as forum_slug,
                    case when f.parent = 0 then f.group_id + 1000 else f.parent end as parent_id
                from :_sfforums f
                union
                select
                    1000 + g.group_id,
                    g.group_name,
                    g.group_seq,
                    g.group_desc,
                    null,
                    null
                from :_sfgroups g",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function tags(ExportModel $ex): void
    {
        if ($ex->exists('sftags')) {
            // Tags
            $tag_Map = array(
                'tag_id' => 'TagID',
                'tag_name' => 'Name'
            );
            $ex->export('Tag', "select * from :_sftags", $tag_Map);

            if ($ex->exists('sftagmeta')) {
                $tagDiscussion_Map = array(
                    'tag_id' => 'TagID',
                    'topic_id' => 'DiscussionID'
                );
                $ex->export('TagDiscussion', "select * from :_sftagmeta", $tagDiscussion_Map);
            }
        }
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
            'topic_id' => 'DiscussionID',
            'forum_id' => 'CategoryID',
            'user_id' => 'InsertUserID',
            'topic_name' => 'Name',
            'Format' => 'Format',
            'topic_date' => 'DateInserted',
            'topic_pinned' => 'Announce',
            'topic_slug' => array('Column' => 'Slug', 'Type' => 'varchar(200)')
        );
        $ex->export(
            'Discussion',
            "select t.*, 'Html' as Format from :_sftopics t",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $comment_Map = array(
            'post_id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'post_content' => 'Body',
            'Format' => 'Format',
            'user_id' => 'InsertUserID',
            'post_date' => 'DateInserted',
            'poster_ip' => 'InsertIPAddress'
        );
        $ex->export(
            'Comment',
            "select p.*, 'Html' as Format from :_sfposts p",
            $comment_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function conversations(ExportModel $ex): void
    {
        $conv_Map = array(
            'message_id' => 'ConversationID',
            'from_id' => 'InsertUserID',
            'sent_date' => 'DateInserted'
        );
        $ex->export(
            'Conversation',
            "select * from :_sfmessages where is_reply = 0",
            $conv_Map
        );

        // ConversationMessage.
        $convMessage_Map = array(
            'message_id' => 'MessageID',
            'from_id' => 'InsertUserID',
            'message' => array('Column' => 'Body')
        );
        $ex->export(
            'ConversationMessage',
            'select c.message_id as ConversationID, m.*
                from :_sfmessages c
                join :_sfmessages m
                    on (m.is_reply = 0 and m.message_id = c.message_id)
                    or (m.is_reply = 1 and c.is_reply = 0 and m.message_slug = c.message_slug
                    and m.from_id in (c.from_id, c.to_id) and m.to_id in (c.from_id, c.to_id));',
            $convMessage_Map
        );

        // UserConversation
        $userConv_Map = array(
            'message_id' => 'ConversationID',
            'from_id' => 'UserID'
        );
        $ex->export(
            'UserConversation',
            'select message_id, from_id
                from :_sfmessages
                where is_reply = 0
                union
                select message_id, to_id
                from :_sfmessages
                where is_reply = 0',
            $userConv_Map
        );
    }
}
