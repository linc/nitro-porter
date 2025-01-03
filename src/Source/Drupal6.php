<?php

/**
 * Drupal 6 exporter tool
 *
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class Drupal6 extends Source
{
    public const SUPPORTED = [
        'name' => 'Drupal 6',
        'prefix' => '',
        'charset_table' => 'comment',
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
        ]
    ];

    /**
     * @var array Required tables => columns
     */
    public $sourceTables = array();

    /**
     * @param ExportModel $ex
     */
    public function run($ex)
    {
        $this->users($ex);
        $this->signatures($ex);
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
            'uid' => 'UserID',
            'name' => 'Name',
            'Password' => 'Password',
            'mail' => 'Email',
            'photo' => 'Photo',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'login' => array('Column' => 'DateLastActive', 'Filter' => 'timestampToDate')
        );
        $ex->export(
            'User',
            "select u.*,
                    nullif(concat('drupal/', u.picture), 'drupal/') as photo,
                    concat('md5$$', u.pass) as Password,
                    'Django' as HashMethod
                from :_users u
                where uid > 0",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function signatures(ExportModel $ex): void
    {
        $userMeta_Map = array(
            'uid' => 'UserID',
            'Name' => 'Name',
            'signature' => 'Value'
        );
        $ex->export(
            'UserMeta',
            "select u.*, 'Plugins.Signatures.Sig' as Name
                from :_users u
                where uid > 0",
            $userMeta_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $role_Map = array(
            'rid' => 'RoleID',
            'name' => 'Name'
        );
        $ex->export('Role', "select r.* from :_role r", $role_Map);

        // User Role.
        $userRole_Map = array(
            'uid' => 'UserID',
            'rid' => 'RoleID'
        );
        $ex->export(
            'UserRole',
            "select * from :_users_roles",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array(
            'tid' => 'CategoryID',
            'name' => 'Name',
            'description' => 'description',
            'parent' => 'ParentCategoryID'
        );
        $ex->export(
            'Category',
            "select t.*, nullif(h.parent, 0) as parent
                 from :_term_data t
                 join :_term_hierarchy h
                    on t.tid = h.tid",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
            'nid' => 'DiscussionID',
            'title' => 'Name',
            'body' => 'Body',
            'uid' => 'InsertUserID',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'DateUpdated' => array('Column' => 'DateUpdated', 'Filter' => 'timestampToDate'),
            'sticky' => 'Announce',
            'tid' => 'CategoryID'
        );
        $ex->export(
            'Discussion',
            "select n.*, nullif(n.changed, n.created) as DateUpdated, f.tid, r.body
                 from nodeforum f
                 left join node n
                    on f.nid = n.nid
                 left join node_revisions r
                    on r.nid = n.nid",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $comment_Map = array(
            'cid' => 'CommentID',
            'uid' => 'InsertUserID',
            'body' => array('Column' => 'Body'),
            'hostname' => 'InsertIPAddress',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate')
        );
        $ex->export(
            'Comment',
            "select
                    n.created,
                    n.uid,
                    r.body,
                    c.nid as DiscussionID,
                    n.title,
                    'Html' as Format,
                    nullif(n.changed, n.created) as DateUpdated
                 from node n
                 left join node_comments c
                    on c.cid = n.nid
                 left join node_revisions r
                    on r.nid = n.nid
                 where n.type = 'forum_reply'",
            $comment_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function conversations(ExportModel $ex): void
    {
        $conversation_Map = array(
            'thread_id' => 'ConversationID',
            'author' => 'InsertUserID',
            'title' => 'Subject',
        );
        $ex->export(
            'Conversation',
            "select
                    pmi.thread_id,
                    pmm.author,
                    pmm.subject as title,
                    FROM_UNIXTIME(pmm.timestamp) as DateInserted
                from pm_message as pmm
                    inner join pm_index as pmi on pmi.mid = pmm.mid
                        and pmm.author = pmi.uid and pmi.deleted = 0 and pmi.uid > 0
                group by pmi.thread_id;",
            $conversation_Map
        );

        // Conversation Messages.
        $conversationMessage_Map = array(
            'mid' => 'MessageID',
            'thread_id' => 'ConversationID',
            'author' => 'InsertUserID'
        );
        $ex->export(
            'ConversationMessage',
            "select
                    pmm.mid,
                    pmi.thread_id,
                    pmm.author,
                    FROM_UNIXTIME(pmm.timestamp) as DateInserted,
                    pmm.body as Body,
                    'Html' as Format
                from pm_message as pmm
                    inner join pm_index as pmi on pmi.mid = pmm.mid AND pmi.deleted = 0 and pmi.uid > 0;",
            $conversationMessage_Map
        );

        // User Conversation.
        $userConversation_Map = array(
            'uid' => 'UserID',
            'thread_id' => 'ConversationID'
        );
        $ex->export(
            'UserConversation',
            "select
                    pmi.uid,
                    pmi.thread_id,
                    0 as Deleted
                from pm_index as pmi
                    inner join pm_message as pmm ON pmm.mid = pmi.mid
                where pmi.deleted = 0
                    and pmi.uid > 0
                group by
                    pmi.uid,
                    pmi.thread_id;",
            $userConversation_Map
        );
    }
}
