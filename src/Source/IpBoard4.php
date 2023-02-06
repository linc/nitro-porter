<?php

/**
 * Invision Powerboard 4.x exporter tool.
 *
 * To export avatars, provide ?db-avatars=1&avatars-source=/path/to/avatars
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class IpBoard4 extends Source
{
    public const SUPPORTED = [
        'name' => 'IP.Board 4',
        'prefix' => 'ibf_',
        'charset_table' => 'posts',
        'hashmethod' => 'ipb',
        'options' => [
            'avatars-source' => [
                'Full path of source avatars to process.',
                'Sx' => ':',
            ],
            'users-source' => [
                'Source user table: profile_portal (default) or member_extra.',
                'Sx' => ':',
            ],
        ],
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 1,
            'PrivateMessages' => 1,
            'Signatures' => 1,
            'Attachments' => 1,
            'Bookmarks' => 0,
            'Permissions' => 1,
            'Badges' => 0,
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 1,
        ]
    ];

    /**
     * @param ExportModel $ex
     */
    public function run(ExportModel $ex)
    {
        $this->users($ex);
        $this->roles($ex);
        $this->userMeta($ex);

        $this->categories($ex);
        $this->discussions($ex);
        $this->tags($ex);
        $this->comments($ex);
        $this->attachments($ex);

        $this->conversations($ex);
    }

    /**
     * @param ExportModel $ex
     */
    protected function conversations(ExportModel $ex)
    {
        // Conversations.
        $conversation_Map = array(
            'mt_id' => 'ConversationID',
            'mt_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'mt_title' => 'Subject',
            'mt_starter_id' => 'InsertUserID'
        );
        $sql = "select * from :_message_topics where mt_is_deleted = 0";

        $ex->export('Conversation', $sql, $conversation_Map);

        // Conversation Message.
        $conversationMessage_Map = array(
            'msg_id' => 'MessageID',
            'msg_topic_id' => 'ConversationID',
            'msg_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'msg_post' => 'Body',
            'Format' => 'Format',
            'msg_author_id' => 'InsertUserID',
            'msg_ip_address' => 'InsertIPAddress'
        );
        $sql = "select m.*, 'IPB' as Format from :_message_posts m";

        $ex->export('ConversationMessage', $sql, $conversationMessage_Map);

        // User Conversation.
        $userConversation_Map = array(
            'map_user_id' => 'UserID',
            'map_topic_id' => 'ConversationID',
            'Deleted' => 'Deleted'
        );
        $sql = "select t.*,
            !map_user_active as Deleted
            from :_message_topic_user_map t";
        $ex->export('UserConversation', $sql, $userConversation_Map);
    }

    /**
     * @param string $memberID
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex)
    {
        $user_Map = array(
            'member_id' => 'UserID',
            'members_display_name' => array('Column' => 'Name', 'Filter' => 'HtmlDecoder'),
            'email' => 'Email',
            'joined' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'firstvisit' => array(
                'Column' => 'DateFirstVisit',
                'SourceColumn' => 'joined',
                'Filter' => 'timestampToDate'
            ),
            'ip_address' => 'InsertIPAddress',
            'time_offset' => 'HourOffset',
            'last_activity' => array('Column' => 'DateLastActive', 'Filter' => 'timestampToDate'),
            'member_banned' => 'Banned',
            'Photo' => 'Photo',
            'title' => 'Title',
            'location' => 'Location'
        );

        $from = '';
        if ($ex->exists('members', 'members_pass_hash') === true) {
            $select = ",concat(m.members_pass_hash, '$', m.members_pass_salt) as Password";
        } else {
            $select = ",concat(mc.converge_pass_hash, '$', mc.converge_pass_salt) as Password";
            $from = "left join :_members_converge mc
            on m.member_id = mc.converge_id";
        }

        if ($ex->exists('members', 'hide_email') === true) {
            $showEmail = '!hide_email';
        } else {
            $showEmail = '0';
        }

        $cdn = $this->cdnPrefix();

        if ($ex->exists('member_extra') === true) {
            $sql = "select m.*,
                m.joined as firstvisit,
                'ipb' as HashMethod,
                 $showEmail as ShowEmail,
                case when x.avatar_location in ('noavatar', '') then null
                    when x.avatar_location like 'upload:%'
                        then concat('{$cdn}ipb/', right(x.avatar_location, length(x.avatar_location) - 7))
                    when x.avatar_type = 'upload' then concat('{$cdn}ipb/', x.avatar_location)
                    when x.avatar_type = 'url' then x.avatar_location
                    when x.avatar_type = 'local' then concat('{$cdn}style_avatars/', x.avatar_location)
                    else null
                end as Photo,
                x.location
                $select
            from :_members m
            left join :_member_extra x
                on m.member_id = x.id
                $from";
        } else {
            $sql = "select m.*,
                joined as firstvisit,
                'ipb' as HashMethod,
                 $showEmail as ShowEmail,
                case when length(p.pp_main_photo) <= 3 or p.pp_main_photo is null then null
                    when p.pp_main_photo like '%//%' then p.pp_main_photo
                    else concat('{$cdn}ipb/', p.pp_main_photo)
                end as Photo
                $select
                from :_members m
                left join :_profile_portal p
                    on m.member_id = p.pp_member_id
                $from";
        }

        $ex->export('User', $sql, $user_Map);
    }

    /**
     * @param ExportModel $ex
     * @param string $memberID
     */
    protected function roles(ExportModel $ex)
    {
        $role_Map = array(
            'g_id' => 'RoleID',
            'g_title' => 'Name'
        );
        $ex->export('Role', "select * from :_groups", $role_Map);

        // User Role.
        $userRole_Map = array(
            'member_id' => 'UserID',
            'member_group_id' => 'RoleID'
        );

        $sql = "
         select
            m.$memberID, m.$groupID
         from :_members m";

        if ($ex->exists('members', 'mgroup_others')) {
            $sql .= "
            union all
            select m.$memberID, g.g_id
            from :_members m
            join :_groups g
               on find_in_set(g.g_id, m.mgroup_others)";
        }

        $ex->export('UserRole', $sql, $userRole_Map);
    }

    /**
     * @param ExportModel $ex
     * @return false|string
     */
    protected function userMeta(ExportModel $ex)
    {
        $userMeta_Map = array(
            'UserID' => 'UserID',
            'Name' => 'Name',
            'Value' => 'Value'
        );

        if ($ex->exists('profile_portal', 'signature') === true) {
            $sql = "
         select
            pp_member_id as UserID,
            'Plugin.Signatures.Sig' as Name,
            signature as Value
         from :_profile_portal
         where length(signature) > 1
         union all
         select
            pp_member_id as UserID,
            'Plugin.Signatures.Format' as Name,
            'IPB' as Value
         from :_profile_portal
         where length(signature) > 1
               ";
        } elseif ($ex->exists('member_extra', array('id', 'signature')) === true) {
            $sql = "
         select
            id as UserID,
            'Plugin.Signatures.Sig' as Name,
            signature as Value
         from :_member_extra
         where length(signature) > 1
         union all
         select
            id as UserID,
            'Plugin.Signatures.Format' as Name,
            'IPB' as Value
         from :_member_extra
         where length(signature) > 1";
        } else {
            $sql = false;
        }
        if ($sql) {
            $ex->export('UserMeta', $sql, $userMeta_Map);
        }
        return $sql;
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array(
            'id' => 'CategoryID',
            'name' => array('Column' => 'Name', 'Filter' => 'HtmlDecoder'),
            'name_seo' => 'UrlCode',
            'description' => 'Description',
            'parent_id' => 'ParentCategoryID',
            'position' => 'Sort'
        );
        $ex->export('Category', "select * from :_forums", $category_Map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex)
    {
        $descriptionSQL = 'p.post';
        $hasTopicDescription = ($ex->exists('topics', array('description')) === true);
        if ($hasTopicDescription || $ex->exists('posts', array('description')) === true) {
            $description = ($hasTopicDescription) ? 't.description' : 'p.description';
            $descriptionSQL = "case
                when $description <> '' and p.post is not null
                    then concat('<div class=\"IPBDescription\">', $description, '</div>', p.post)
                when $description <> '' then $description
                else p.post
            end";
        }
        $discussion_Map = array(
            'tid' => 'DiscussionID',
            'title' => 'Name',
            'description' => array('Column' => 'SubName', 'Type' => 'varchar(255)'),
            'forum_id' => 'CategoryID',
            'starter_id' => 'InsertUserID',
            'start_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'ip_address' => 'InsertIPAddress',
            'edit_time' => array('Column' => 'DateUpdated', 'Filter' => 'timestampToDate'),
            //          'last_post' => array('Column' => 'DateLastPost', 'Filter' => array($ex, 'timestampToDate')),
            'posts' => 'CountComments',
            'views' => 'CountViews',
            'pinned' => 'Announce',
            'post' => 'Body',
            'closed' => 'Closed'
        );
        $sql = "select t.*,
            $descriptionSQL as post,
            case when t.state = 'closed' then 1 else 0 end as closed,
            'BBCode' as Format,
            p.ip_address,
            p.edit_time
        from :_topics t
        left join :_posts p
            on t.topic_firstpost = p.pid
        where t.tid between {from} and {to}";

        $ex->export('Discussion', $sql, $discussion_Map);
    }

    /**
     * @param ExportModel $ex
     * @return string
     */
    protected function tags(ExportModel $ex): string
    {
        $ex->query("DROP TABLE IF EXISTS `z_tag` ");
        $ex->query(
            "CREATE TABLE `z_tag` (
                `TagID` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `FullName` varchar(50) DEFAULT NULL,
                PRIMARY KEY (`TagID`),
                UNIQUE KEY `FullName` (`FullName`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
        );
        $ex->query("insert into z_tag (FullName) (select distinct t.tag_text as FullName from :_core_tags t)");

        $tagDiscussion_Map = array(
            'tag_added' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
        );
        $sql = "select TagID, '0' as CategoryID, tag_meta_id as DiscussionID, t.tag_added
            from :_core_tags t
            left join z_tag zt
                on t.tag_text = zt.FullName";
        $ex->export('TagDiscussion', $sql, $tagDiscussion_Map);

        $tag_Map = array(
            'FullName' => 'FullName',
            'FullNameToName' => array('Column' => 'Name', 'Filter' => 'formatUrl')
        );
        $sql = "select TagID, FullName, FullName as FullNameToName from z_tag zt";
        $ex->export('Tag', $sql, $tag_Map);
        return $sql;
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex)
    {
        $comment_Map = array(
            'pid' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'author_id' => 'InsertUserID',
            'ip_address' => 'InsertIPAddress',
            'post_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'edit_time' => array('Column' => 'DateUpdated', 'Filter' => 'timestampToDate'),
            'post' => 'Body'
        );
        $sql = "select p.*,
                'BBCode' as Format
            from :_posts p
            join :_topics t
                on p.topic_id = t.tid
            where p.pid between {from} and {to}
                and p.pid <> t.topic_firstpost";

        $ex->export('Comment', $sql, $comment_Map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function attachments(ExportModel $ex)
    {
        $media_Map = array(
            'attach_id' => 'MediaID',
            'atype_mimetype' => 'Type',
            'attach_file' => 'Name',
            'attach_path' => 'Path',
            'attach_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'thumb_path' => array('Column' => 'ThumbPath', 'Filter' => array($this, 'filterThumbnailData')),
            'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'filterThumbnailData')),
            'attach_member_id' => 'InsertUserID',
            'attach_filesize' => 'Size',
            'ForeignID' => 'ForeignID',
            'ForeignTable' => 'ForeignTable',
            'img_width' => 'ImageWidth',
            'img_height' => 'ImageHeight'
        );
        $sql = "select a.*,
               concat('~cf/ipb/', a.attach_location) as attach_path,
               concat('~cf/ipb/', a.attach_location) as thumb_path,
               128 as thumb_width,
               ty.atype_mimetype,
               case when p.pid = t.topic_firstpost then 'discussion' else 'comment' end as ForeignTable,
               case when p.pid = t.topic_firstpost then t.tid else p.pid end as ForeignID,
               case a.attach_img_width when 0 then a.attach_thumb_width else a.attach_img_width end as img_width,
               case a.attach_img_height when 0 then a.attach_thumb_height else a.attach_img_height end as img_height
            from :_attachments a
            join :_posts p
               on a.attach_rel_id = p.pid and a.attach_rel_module = 'post'
            join :_topics t
               on t.tid = p.topic_id
            left join :_attachments_type ty
               on a.attach_ext = ty.atype_extension";

        $ex->export('Media', $sql, $media_Map);
    }
}
