<?php

/**
 * bbPress exporter tool
 *
 * @author  Todd Burry
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class BbPress1 extends Source
{
    public const SUPPORTED = [
        'name' => 'bbPress 1',
        'prefix' => 'bb_',
        'charset_table' => 'posts',
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
        ]
    ];

    /**
     * @var array Required tables => columns
     */
    public $sourceTables = array(
        'forums' => array(),
        'posts' => array(),
        'topics' => array(),
        'users' => array('ID', 'user_login', 'user_pass', 'user_email', 'user_registered'),
        'meta' => array()
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
        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);
        $this->conversations($ex);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $user_Map = array(
            'ID' => 'UserID',
            'user_login' => 'Name',
            'user_pass' => 'Password',
            'user_email' => 'Email',
            'user_registered' => 'DateInserted'
        );
        $ex->export('User', "select * from :_users", $user_Map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $ex->export(
            'Role',
            "select 1 as RoleID, 'Guest' as Name
                 union select 2, 'Key Master'
                 union select 3, 'Administrator'
                 union select 4, 'Moderator'
                 union select 5, 'Member'
                 union select 6, 'Inactive'
                 union select 7, 'Blocked'"
        );

        // UserRoles
        $userRole_Map = array(
            'user_id' => 'UserID'
        );
        $ex->export(
            'UserRole',
            "select distinct user_id,
                case when locate('keymaster', meta_value) <> 0 then 2
                when locate('administrator', meta_value) <> 0 then 3
                when locate('moderator', meta_value) <> 0 then 4
                when locate('member', meta_value) <> 0 then 5
                when locate('inactive', meta_value) <> 0 then 6
                when locate('blocked', meta_value) <> 0 then 7
                else 1 end as RoleID
            from :_usermeta
            where meta_key = 'bb_capabilities'",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array(
            'forum_id' => 'CategoryID',
            'forum_name' => 'Name',
            'forum_desc' => 'Description',
            'forum_slug' => 'UrlCode',
            'left_order' => 'Sort'
        );
        $ex->export(
            'Category',
            "select *,
                    lower(forum_slug) as forum_slug,
                    nullif(forum_parent,0) as ParentCategoryID
                 from :_forums",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
            'topic_id' => 'DiscussionID',
            'forum_id' => 'CategoryID',
            'topic_poster' => 'InsertUserID',
            'topic_title' => 'Name',
            'Format' => 'Format',
            'topic_start_time' => 'DateInserted',
            'topic_sticky' => 'Announce'
        );
        $ex->export(
            'Discussion',
            "select t.*,
                    'Html' as Format,
                    case t.topic_open when 0 then 1 else 0 end as Closed
                 from :_topics t
                 where topic_status = 0",
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
            'post_text' => array('Column' => 'Body', 'Filter' => 'bbPressTrim'),
            'Format' => 'Format',
            'poster_id' => 'InsertUserID',
            'post_time' => 'DateInserted'
        );
        $ex->export(
            'Comment',
            "select p.*,
                    'Html' as Format
                 from :_posts p
                 where post_status = 0",
            $comment_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function conversations(ExportModel $ex): void
    {
        // The export is different depending on the table layout.
        $PM = $ex->exists('bbpm', array('ID', 'pm_title', 'pm_from', 'pm_to', 'pm_text', 'sent_on', 'pm_thread'));
        $conversationVersion = '';

        if ($PM === true) {
            // This is from an old version of the plugin.
            $conversationVersion = 'old';
        } elseif ($ex->exists('bbpm', array('ID', 'pm_from', 'pm_text', 'sent_on', 'pm_thread'))) {
            // This is from a newer version of the plugin.
            $conversationVersion = 'new';
        }

        if ($conversationVersion) {
            $conv_Map = array(
                'pm_thread' => 'ConversationID',
                'pm_from' => 'InsertUserID'
            );
            $ex->export(
                'Conversation',
                "select *, from_unixtime(sent_on) as DateInserted
            from :_bbpm
            where thread_depth = 0",
                $conv_Map
            );

            // ConversationMessage.
            $convMessage_Map = array(
                'ID' => 'MessageID',
                'pm_thread' => 'ConversationID',
                'pm_from' => 'InsertUserID',
                'pm_text' => array('Column' => 'Body', 'Filter' => 'bbPressTrim')
            );
            $ex->export(
                'ConversationMessage',
                'select *, from_unixtime(sent_on) as DateInserted
                    from :_bbpm',
                $convMessage_Map
            );

            // UserConversation.
            $ex->query("create temporary table bbpmto (UserID int, ConversationID int)");

            if ($conversationVersion == 'new') {
                $to = $ex->query(
                    "select object_id, meta_value
                        from :_meta
                        where object_type = 'bbpm_thread' and meta_key = 'to'"
                );
                if (is_object($to)) {
                    while ($row = $to->nextResultRow()) {
                        $thread = $row['object_id'];
                        $tos = explode(',', trim($row['meta_value'], ','));
                        $toIns = '';
                        foreach ($tos as $toID) {
                            $toIns .= "($toID,$thread),";
                        }
                        $toIns = trim($toIns, ',');

                        $ex->query("insert bbpmto (UserID, ConversationID) values $toIns");
                    }

                    $ex->export('UserConversation', 'select * from bbpmto');
                }
            } else {
                $conUser_Map = array(
                    'pm_thread' => 'ConversationID',
                    'pm_from' => 'UserID'
                );
                $ex->export(
                    'UserConversation',
                    'select distinct
                            pm_thread,
                            pm_from,
                            del_sender as Deleted
                        from :_bbpm
                        union
                        select distinct
                            pm_thread,
                            pm_to,
                            del_reciever
                        from :_bbpm',
                    $conUser_Map
                );
            }
        }
    }
}
