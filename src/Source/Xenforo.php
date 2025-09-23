<?php

/**
 * Xenforo source package.
 *
 * @author Lincoln Russell, code@lincolnwebs.com
 */

namespace Porter\Source;

use Porter\Config;
use Porter\ConnectionManager;
use Porter\FileTransfer;
use Porter\Source;
use Porter\Migration;

class Xenforo extends Source
{
    public const SUPPORTED = [
        'name' => 'Xenforo',
        'defaultTablePrefix' => 'xf_',
        'charsetTable' => 'post',
        'passwordHashMethod' => 'xenforo',
        'avatarsPrefix' => '1',
        'avatarThumbnailsPrefix' => 'm',
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0, // @todo
            'Roles' => 1,
            'Avatars' => 1,
            'AvatarThumbnails' => 1,
            'PrivateMessages' => 1,
            'Signatures' => 1,
            'Attachments' => 1,
            'Bookmarks' => 0, // @todo
        ]
    ];

    /**
     * Forum-specific export format.
     *
     * @param Migration $port
     */
    public function run(Migration $port): void
    {
        $this->users($port);
        $this->roles($port);
        $this->signatures($port);

        $this->categories($port);
        $this->discussions($port);
        $this->comments($port);
        $this->conversations($port);
        $this->attachments($port);
    }

    /**
     * @param Migration $port
     */
    public function signatures(Migration $port): void
    {
        $sql = "select
                user_id as UserID,
                'Plugin.Signatures.Sig' as Name,
                signature as Value
            from :_user_profile
            where nullif(signature, '') is not null
            union
            select
                user_id,
                'Plugin.Signatures.Format',
                'BBCode'
            from :_user_profile
            where nullif(signature, '') is not null";
        $port->export('UserMeta', $sql);
    }

    /**
     * @param Migration $port
     */
    protected function users(Migration $port): void
    {
        $user_Map = array(
            'user_id' => 'UserID',
            'username' => 'Name',
            'email' => 'Email',
            'custom_title' => 'Title',
            'register_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'last_activity' => array('Column' => 'DateLastActive', 'Filter' => 'timestampToDate'),
            'is_admin' => 'Admin',
            'is_banned' => 'Banned',
            'password' => 'Password',
            'hash_method' => 'HashMethod',
            'avatar' => 'Photo'
        );
        $port->export(
            'User',
            "select u.*,
                    ua.data as password,
                    'xenforo' as hash_method,
                    case when u.avatar_date > 0 then concat('xf/', u.user_id div 1000, '/', u.user_id, '.jpg')
                        else null end as avatar
                from :_user u
                left join :_user_authenticate ua
                    on u.user_id = ua.user_id",
            $user_Map
        );
    }

    /**
     * @param Migration $port
     */
    protected function roles(Migration $port): void
    {
        $role_Map = array(
            'user_group_id' => 'RoleID',
            'title' => 'Name'
        );
        $port->export(
            'Role',
            "select * from :_user_group",
            $role_Map
        );

        // User Roles.
        $userRole_Map = array(
            'user_id' => 'UserID',
            'user_group_id' => 'RoleID'
        );

        $port->export(
            'UserRole',
            "select user_id, user_group_id
                from :_user
                union all
                select u.user_id, ua.user_group_id
                from :_user u
                join :_user_group ua
                    on find_in_set(ua.user_group_id, u.secondary_group_ids)",
            $userRole_Map
        );
    }

    /**
     * @param Migration $port
     */
    protected function categories(Migration $port): void
    {
        $category_Map = array(
            'node_id' => 'CategoryID',
            'title' => 'Name',
            'description' => 'Description',
            'parent_node_id' => array(
                'Column' => 'ParentCategoryID',
                'Filter' => function ($value) {
                    return $value ? $value : null;
                }
            ),
            'display_order' => 'Sort',
            'display_in_list' => array('Column' => 'HideAllDiscussions', 'Filter' => 'NotFilter')
        );
        $port->export(
            'Category',
            "select n.* from :_node n",
            $category_Map
        );
    }

    /**
     * @param Migration $port
     */
    protected function discussions(Migration $port): void
    {
        $discussion_Map = array(
            'thread_id' => 'DiscussionID',
            'node_id' => 'CategoryID',
            'title' => 'Name',
            'view_count' => 'CountViews',
            'user_id' => 'InsertUserID',
            'post_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'sticky' => 'Announce',
            'discussion_open' => array('Column' => 'Closed', 'Filter' => 'NotFilter'),
            'last_post_date' => array('Column' => 'DateLastComment', 'Filter' => 'timestampToDate'),
            'message' => 'Body',
            'format' => 'Format',
            'ip' => array('Column' => 'InsertIPAddress', 'Filter' => 'long2ipf')
        );
        $port->export(
            'Discussion',
            "select t.*,
                p.message,
                'BBCode' as format,
                ip.ip
            from :_thread t
            join :_post p
                on t.first_post_id = p.post_id
            left join :_ip ip
                on p.ip_id = ip.ip_id",
            $discussion_Map
        );
    }

    /**
     * @param Migration $port
     */
    protected function comments(Migration $port): void
    {
        $comment_Map = array(
            'post_id' => 'CommentID',
            'thread_id' => 'DiscussionID',
            'user_id' => 'InsertUserID',
            'post_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'message' => 'Body',
            'format' => 'Format',
            'ip' => array('Column' => 'InsertIPAddress', 'Filter' => 'long2ipf')
        );
        $port->export(
            'Comment',
            "select p.*,
                'BBCode' as format,
                ip.ip
            from :_post p
            join :_thread t
                on p.thread_id = t.thread_id
            left join :_ip ip
                on p.ip_id = ip.ip_id
            where p.post_id <> t.first_post_id
                and message_state = 'visible'",
            $comment_Map
        );
    }

    /**
     * Export attachments.
     *
     * Real-world example set that consistently refers to the same upload (for real):
     *  URL example: `/attachments/7590ax-webp.227/`
     *  URL format: `'/attachments/' .
     *   str_replace('.', '-', '{attachment_data.filename}') . '.{attachment.attachment_id}'`
     *  Thumbnail path example: `/attachments/0/13-cbec5592e1d5cd9d2f783b4039c4ce6e.jpg`
     *  Thumbnail path format: '/attachments/0/{attachment_data.data_id}-{attachment_data.file_key}.jpg'
     *  Original path example: `/internal_data/attachments/0/13-cbec5592e1d5cd9d2f783b4039c4ce6e.data`
     *  Original path format: `'/internal_data/attachments/0/{attachment_data.data_id}-{attachment_data.file_key}.data'`
     *
     * Captured in late 2025 from Xenforo v2.3.6.
     *
     * Schema magic values: `attachment.content_type`: `post` | `conversation_message`
     *
     * Xenforo faithfully reimplemented vBulletin's worst ideas here, probably a misguided security effort.
     * Most other platforms don't jank filenames like this, so rebuild Path as {id}-{filename} to avoid conflicts.
     * @see self::attachmentsData() for the `FileTransfer` data to complete the file renaming.
     *
     * @param Migration $port
     */
    protected function attachments(Migration $port): void
    {
        $map = [
            'attachment_id' => 'MediaID',
            'filename' => 'Name',
            'file_size' => 'Size',
            'user_id' => 'InsertUserID',
            'upload_date' => 'DateInserted',
            'width' => 'ImageWidth',
            'height' => 'ImageHeight',
        ];
        $filters = [];

        $prx = $port->dbInput()->getTablePrefix();
        $wrt = Config::getInstance()->get('option_attachments_webroot');
        $builder = new \Staudenmeir\LaravelCte\Query\Builder($port->dbInput()); // @todo f.
        $query = $builder
            ->from('attachment', 'a')
            ->select([
                'a.attachment_id',
                'ad.filename',
                'ad.file_size',
                'ad.user_id',
                'ad.width',
                'ad.height',
                'ap.ForeignID',
                'ap.ForeignTable',
            ])
            ->selectRaw("concat('($wrt}', {$prx}a.data_id, '-', replace({$prx}ad.filename, ' ', '_')) as Path")
            ->selectRaw("concat('($wrt}', {$prx}a.data_id, '-', replace({$prx}ad.filename, ' ', '_')) as ThumbPath")
            ->selectRaw("from_unixtime({$prx}ad.upload_date) as DateInserted")
            ->join('attachment_data as ad', 'ad.data_id', '=', 'a.data_id')
            // Build a CET of attached post data & join it.
            ->withExpression('ap', function (\Staudenmeir\LaravelCte\Query\Builder $query) {
                $prx = $query->connection->getTablePrefix();
                $query->from('post', 'p')
                    ->select(['post_id'])
                    ->selectRaw("if({$prx}p.post_id = {$prx}t.first_post_id,
                        {$prx}t.thread_id, {$prx}p.post_id) as ForeignID")
                    ->selectRaw("if({$prx}p.post_id = {$prx}t.first_post_id, 'discussion', 'comment') as ForeignTable")
                    ->join('thread as t', "t.thread_id", '=', "p.thread_id")
                    ->where("p.message_state", '<>', "deleted");
            })
            ->join('ap', 'post_id', '=', 'a.content_id')
            ->where('a.content_type', '=', "post");

        $port->export('Media', $query, $map, $filters);
    }

    /**
     * @param Migration $port
     */
    protected function conversations(Migration $port): void
    {
        $conversation_Map = array(
            'conversation_id' => 'ConversationID',
            'title' => 'Subject',
            'user_id' => 'InsertUserID',
            'start_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate')
        );
        $port->export(
            'Conversation',
            "select *, substring(title, 1, 200) as title from :_conversation_master",
            $conversation_Map
        );

        $conversationMessage_Map = array(
            'message_id' => 'MessageID',
            'conversation_id' => 'ConversationID',
            'message_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'user_id' => 'InsertUserID',
            'message' => 'Body',
            'format' => 'Format',
            'ip' => array('Column' => 'InsertIPAddress', 'Filter' => 'long2ipf')
        );
        $port->export(
            'ConversationMessage',
            "select m.*,
                    'BBCode' as format,
                    ip.ip
                from :_conversation_message m
                left join :_ip ip
                    on m.ip_id = ip.ip_id",
            $conversationMessage_Map
        );

        $userConversation_Map = array(
            'conversation_id' => 'ConversationID',
            'user_id' => 'UserID',
            'Deleted' => 'Deleted'
        );
        $port->export(
            'UserConversation',
            "select
                    r.conversation_id,
                    user_id,
                    case when r.recipient_state = 'deleted' then 1 else 0 end as Deleted
                from :_conversation_recipient r
                union all
                select
                    cu.conversation_id,
                    cu.owner_user_id,
                    0
                from :_conversation_user cu",
            $userConversation_Map
        );
    }

    /**
     * Query builder that selects values `sourcename` & `targetname`.
     *
     * @throws \Exception
     */
    public function attachmentsData(\Illuminate\Database\Connection $c): \Illuminate\Database\Query\Builder
    {
        return $c->table('attachment', 'a')
            ->selectRaw("CONCAT(ad.data_id, '-', ad.file_hash, '.data') as sourcename")
            ->selectRaw("CONTACT(ad.data_id, '-', ad.filename) as targetname")
            ->join(
                'attachment_data as ad',
                'attachment.data_id',
                '=',
                'ad.data_id',
                'left'
            )->where('a.content_type', '=', 'post');
    }
}
