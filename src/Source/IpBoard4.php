<?php

/**
 * Invision Powerboard 4.x exporter tool.
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
        'charset_table' => 'forums_posts',
        'hashmethod' => 'ipb',
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'PrivateMessages' => 1,
            'Attachments' => 1,
            'Bookmarks' => 0,
            'Badges' => 0,
            'Tags' => 0,
        ]
    ];

    /**
     * @param ExportModel $ex
     */
    public function run(ExportModel $ex): void
    {
        $this->users($ex);
        $this->roles($ex);

        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);
        $this->attachments($ex);

        $this->conversations($ex);
    }

    /**
     * @param ExportModel $ex
     */
    protected function conversations(ExportModel $ex): void
    {
        // Conversations.
        $map = [
            'mt_id' => 'ConversationID',
            'mt_date' => 'DateInserted',
            'mt_title' => 'Subject',
            'mt_starter_id' => 'InsertUserID'
        ];
        $filters = [
            'mt_date' => 'timestampToDate',
        ];
        $query = "select * from :_core_message_topics where mt_is_deleted = 0";
        $ex->export('Conversation', $query, $map, $filters);

        // Conversation Message.
        $map = [
            'msg_id' => 'MessageID',
            'msg_topic_id' => 'ConversationID',
            'msg_date' => 'DateInserted',
            'msg_post' => 'Body',
            'msg_author_id' => 'InsertUserID',
            'msg_ip_address' => 'InsertIPAddress'
        ];
        $filters = [
            'msg_date' => 'timestampToDate',
        ];
        $query = "select m.*, 'IPB' as Format from :_core_message_posts m";
        $ex->export('ConversationMessage', $query, $map, $filters);

        // User Conversation.
        $map = [
            'map_user_id' => 'UserID',
            'map_topic_id' => 'ConversationID',
        ];
        $query = "select t.*, !map_user_active as Deleted
            from :_core_message_topic_user_map t";
        $ex->export('UserConversation', $query, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $map = [
            'member_id' => 'UserID',
            'name' => 'Name',
            'email' => 'Email',
            'joined' => 'DateInserted',
            'ip_address' => 'InsertIPAddress',
            'time_offset' => 'HourOffset',
            'last_activity' => 'DateLastActive',
            'member_banned' => 'Banned',
            'title' => 'Title',
            'location' => 'Location'
        ];
        $filters = [
            'name' => 'HtmlDecoder',
            'joined' => 'timestampToDate',
            'last_activity' => 'timestampToDate',
        ];
        $query = "select m.*, 'ipb' as HashMethod
            from :_core_members m";
        $ex->export('User', $query, $map, $filters);
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $map = [
            'g_id' => 'RoleID',
            'g_title' => 'Name'
        ];
        $ex->export('Role', "select * from :_core_groups", $map);

        // User Role.
        $map = [
            'member_id' => 'UserID',
            'member_group_id' => 'RoleID'
        ];
        $query = "select m.member_id, m.member_group_id from :_core_members m";
        $ex->export('UserRole', $query, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $map = [
            'id' => 'CategoryID',
            'name' => 'Name',
            'name_seo' => 'UrlCode',
            'description' => 'Description',
            'parent_id' => 'ParentCategoryID',
            'position' => 'Sort'
        ];
        $filters = [
            'name' => 'HtmlDecoder',
        ];
        $ex->export('Category', "select * from :_forums_forums", $map, $filters);
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $descriptionSQL = 'p.post';
        $hasTopicDescription = ($ex->exists('forums_topics', array('description')) === true);
        if ($hasTopicDescription || $ex->exists('forums_posts', array('description')) === true) {
            $description = ($hasTopicDescription) ? 't.description' : 'p.description';
            $descriptionSQL = "case
                when $description <> '' and p.post is not null
                    then concat('<div class=\"IPBDescription\">', $description, '</div>', p.post)
                when $description <> '' then $description
                else p.post
            end";
        }
        $map = [
            'tid' => 'DiscussionID',
            'title' => 'Name',
            'description' => 'SubName',
            'forum_id' => 'CategoryID',
            'starter_id' => 'InsertUserID',
            'start_date' => 'DateInserted',
            'edit_time' => 'DateUpdated',
            'posts' => 'CountComments',
            'views' => 'CountViews',
            'pinned' => 'Announce',
            'post' => 'Body',
            'closed' => 'Closed'
        ];
        $filters = [
            'start_date' => 'timestampToDate',
            'edit_time' => 'timestampToDate',
        ];
        $query = "select t.*,
                $descriptionSQL as post,
                IF(t.state = 'closed', 1, 0) as closed,
                'BBCode' as Format,
                p.edit_time
            from :_forums_topics t
            left join :_forums_posts p
                on t.topic_firstpost = p.pid";
        $ex->export('Discussion', $query, $map, $filters);
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $map = [
            'pid' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'author_id' => 'InsertUserID',
            'ip_address' => 'InsertIPAddress',
            'post_date' => 'DateInserted',
            'edit_time' => 'DateUpdated',
            'post' => 'Body'
        ];
        $filters = [
            'post_date' => 'timestampToDate',
            'edit_time' => 'timestampToDate',
        ];
        $query = "select p.*,
                'BBCode' as Format
            from :_forums_posts p
            join :_forums_topics t
                on p.topic_id = t.tid
            where p.pid <> t.topic_firstpost";
        $ex->export('Comment', $query, $map, $filters);
    }

    /**
     * @param ExportModel $ex
     */
    protected function attachments(ExportModel $ex): void
    {
        $map = [
            'attach_id' => 'MediaID',
            'attach_file' => 'Name',
            'attach_path' => 'Path',
            'attach_date' => 'DateInserted',
            'thumb_path' => 'ThumbPath',
            'thumb_width' => 'ThumbWidth',
            'attach_member_id' => 'InsertUserID',
            'attach_filesize' => 'Size',
            'img_width' => 'ImageWidth',
            'img_height' => 'ImageHeight'
        ];
        $filters = [
            'attach_date' => 'timestampToDate',
        ];
        $query = "select a.*
            from :_core_attachments a";
        $ex->export('Media', $query, $map, $filters);
    }
}
