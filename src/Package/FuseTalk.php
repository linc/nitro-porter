<?php

/**
 * FuseTalk exporter tool.
 *
 * You need to convert the database to MySQL first.
 * Use that: https://github.com/tburry/dbdump
 *
 * Tested with FuseTalk Enterprise Edition v4.0
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Alexandre Chouinard
 */

namespace Porter\Package;

use Porter\ExportController;
use Porter\ExportModel;

class FuseTalk extends ExportController
{
    public const SUPPORTED = [
        'name' => 'FuseTalk',
        'prefix' => 'ftdb_',
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 1,
            'Roles' => 1,
            'Avatars' => 0,
            'PrivateMessages' => 1,
            'Signatures' => 1,
            'Attachments' => 0,
            'Bookmarks' => 0,
            'Permissions' => 0,
            'Badges' => 0,
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 0,
            'Reactions' => 0,
            'Articles' => 0,
        ]
    ];

    /**
     * @var array Required tables => columns
     */
    protected $sourceTables = array(
        'categories' => array(),
        'forums' => array(),
        'threads' => array(),
        'messages' => array(),
        'users' => array(),
    );

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see   $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex)
    {
        $characterSet = $ex->getCharacterSet('messages');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->beginExport('', 'FuseTalk');

        $this->createIndices($ex); // Speed up the export.

        $this->users($ex);

        $this->signatures($ex);
        $this->roles($ex);

        $this->conversations($ex);

        $this->categories($ex);

        $this->discussions($ex);

        $this->comments($ex);

        $ex->endExport();
    }

    /**
     * Fix smileys URL
     *
     * @param  $value Value of the current row
     * @param  $field Name associated with the current field value
     * @param  $row   Full data row columns
     * @return string Body
     */
    public function fixSmileysURL($value, $field, $row)
    {
        static $smileySearch = '<img src="i/expressions/';
        static $smileyReplace;

        if ($smileyReplace === null) {
            $smileyReplace = '<img src=' . rtrim($this->cdnPrefix(), '/') . '/expressions/';
        }

        if (strpos($value, $smileySearch) !== false) {
            $value = str_replace($smileySearch, $smileyReplace, $value);
        }

        return $value;
    }

    /**
     * @param ExportModel $ex
     */
    protected function createIndices(ExportModel $ex): void
    {
        $ex->comment("Creating indexes... ");

        if (!$ex->indexExists('ix_users_userid', ':_users')) {
            $ex->query('create index ix_users_userid on :_users (iuserid)');
        }
        if (!$ex->indexExists('ix_banning_banstring', ':_banning')) {
            $ex->query('create index ix_banning_banstring on :_banning (vchbanstring)');
        }
        if (!$ex->indexExists('ix_forumusers_userid', ':_forumusers')) {
            $ex->query('create index ix_forumusers_userid on :_forumusers (iuserid)');
        }
        if (!$ex->indexExists('ix_groupusers_userid', ':_groupusers')) {
            $ex->query('create index ix_groupusers_userid on :_groupusers (iuserid)');
        }
        if (!$ex->indexExists('ix_privatemessages_vchusagestatus', ':_privatemessages')) {
            $ex->query('create index ix_privatemessages_vchusagestatus on :_privatemessages (vchusagestatus)');
        }
        if (!$ex->indexExists('ix_threads_id_pollflag', ':_threads')) {
            $ex->query('create index ix_threads_id_pollflag on :_threads (ithreadid, vchpollflag)');
        }
        if (!$ex->indexExists('ix_threads_poll', ':_threads')) {
            $ex->query('create index ix_threads_poll on :_threads (vchpollflag)');
        }

        $ex->comment("Indexes done!");
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $user_Map = array();
        $ex->exportTable(
            'User',
            "
            select
                user.iuserid as UserID,
                user.vchnickname as Name,
                user.vchemailaddress as Email,
                user.vchpassword as Password,
                'md5' as HashMethod,
                if (forumusers.vchauthoricon is not null,
                    concat('authoricons/', forumusers.vchauthoricon), null) as Photo,
                user.dtinsertdate as DateInserted,
                user.dtlastvisiteddate as DateLastActive,
                user.bapproved as Confirmed,
                if (user.iuserlevel = 0, 1, 0) as Admin,
                if (coalesce(bemail.vchbanstring, bname.vchbanstring, 0) != 0, 1, 0) as Banned
            from :_users as user
                left join :_forumusers as forumusers using (iuserid)
                left join :_banning as bemail on b.vchbanstring = user.vchemailaddress
                left join :_banning as bname on b.vchbanstring = user.vchnickname
            group by user.iuserid
         ;",
            $user_Map
        );  // ":_" will be replaced by database prefix
    }

    /**
     * @param ExportModel $ex
     */
    protected function signatures(ExportModel $ex): void
    {
        $ex->exportTable(
            'UserMeta',
            "
            select
                user.iuserid as UserID,
                'Plugin.Signatures.Sig' as Name,
                user.txsignature as Value
            from :_users as user
            where nullif(nullif(user.txsignature, ''), char(0)) is not null

            union all

            select
                user.iuserid,
                'Plugin.Signatures.Format',
                'Html'
            from :_users as user
            where nullif(nullif(user.txsignature, ''), char(0)) is not null
        "
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $memberRoleID = 1;
        $result = $ex->query("select max(igroupid) as maxRoleID from :_groups", true);
        if ($row = $result->nextResultRow()) {
            $memberRoleID += $row['maxRoleID'];
        }

        // Role.
        $role_Map = array();
        $ex->exportTable(
            'Role',
            "
            select
                groups.igroupid as RoleID,
                groups.vchgroupname as Name
            from :_groups as groups

            union all

            select
                $memberRoleID as RoleID,
                'Members'
            from dual
        ",
            $role_Map
        );

        // User Role.
        $userRole_Map = array();
        $ex->exportTable(
            'UserRole',
            "
            select
                user.iuserid as UserID,
                ifnull (user_role.igroupid, $memberRoleID) as RoleID
            from :_users as user
                left join :_groupusers as user_role using (iuserid)
        ",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function conversations(ExportModel $ex): void
    {
        $ex->query("drop table if exists zConversations;");
        $ex->query(
            "
            create table zConversations(
                `ConversationID` int(11) not null AUTO_INCREMENT,
                `User1` int(11) not null,
                `User2` int(11) not null,
                `DateInserted` datetime not null,
                primary key (`ConversationID`),
                key `IX_zConversation_User1_User2` (`User1`,`User2`)
            );
        "
        );
        $ex->query(
            "
            insert into zConversations(`User1`, `User2`, `DateInserted`)
                select
                    if (pm.iuserid < pm.iownerid, pm.iuserid, pm.iownerid) as User1,
                    if (pm.iuserid < pm.iownerid, pm.iownerid, pm.iuserid) as User2,
                    min(pm.dtinsertdate)
                from :_privatemessages as pm
                group by
                    User1,
                    User2
        "
        );

        // Conversations.
        $conversation_Map = array();
        $ex->exportTable(
            'Conversation',
            "
            select
                c.ConversationID as ConversationID,
                c.User1 as InsertUserID,
                c.DateInserted as DateInserted
            from zConversations as c
        ;",
            $conversation_Map
        );

        // Conversation Messages.
        $conversationMessage_Map = array(
            'txmessage' => array('Column' => 'Body', 'Filter' => array($this, 'fixSmileysURL')),
        );
        $ex->exportTable(
            'ConversationMessage',
            "
            select
                pm.imessageid as MessageID,
                c.ConversationID,
                pm.txmessage,
                'Html' as Format,
                pm.iownerid as InsertUserID,
                pm.dtinsertdate as DateInserted
            from zConversations as c
                inner join :_privatemessages as pm on pm.iuserid = c.User1 and pm.iownerid = c.User2
            where vchusagestatus = 'sent'

            union all

            select
                pm.imessageid as MessageID,
                c.ConversationID,
                pm.txmessage,
                'Html' as Format,
                pm.iownerid as InsertUserID,
                pm.dtinsertdate as DateInserted
            from zConversations as c
                inner join :_privatemessages as pm on pm.iuserid = c.User2 and pm.iownerid = c.User1
            where vchusagestatus = 'sent'
        ;",
            $conversationMessage_Map
        );

        // User Conversation.
        $userConversation_Map = array();
        $ex->exportTable(
            'UserConversation',
            "
            select
                c.ConversationID,
                c.User1 as UserID,
                now() as DateLastViewed
            from zConversations as c

            union all

            select
                c.ConversationID,
                c.User2 as UserID,
                now() as DateLastViewed
            from zConversations as c
        ;",
            $userConversation_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array();
        $ex->exportTable(
            'Category',
            "
            select
                categories.icategoryid as CategoryID,
                categories.vchcategoryname as Name,
                categories.vchdescription as Description,
                -1 as ParentCategoryID
            from :_categories as categories
        ",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        /* Skip "Body". It will be fixed at import.
                 * The first comment is going to be used to fill the missing data and will then be deleted
                 */
        $discussion_Map = array();
        $ex->exportTable(
            'Discussion',
            "
            select
                threads.ithreadid as DiscussionID,
                threads.vchthreadname as Name,
                threads.icategoryid as CategoryID,
                threads.iuserid as InsertUserID,
                threads.dtinsertdate as DateInserted,
                'HTML' as Format,
                if (threads.vchalertthread = 'Yes' and threads.dtstaydate > now(), 2, 0) as Announce,
                if (threads.vchthreadlock = 'Locked', 1, 0) as Closed
            from :_threads as threads
        ",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        /*
                 * The iparentid column doesn't make any sense since the display is
                 * ordered by date only (there are no "sub" comment)
                 */
        $comment_Map = array(
            'txmessage' => array('Column' => 'Body', 'Filter' => array($this, 'fixSmileysURL')),
        );
        $ex->exportTable(
            'Comment',
            "
            select
                messages.imessageid as CommentID,
                messages.ithreadid as DiscussionID,
                messages.iuserid as InsertUserID,
                messages.txmessage,
                'Html' as Format,
                messages.dtmessagedate as DateInserted
            from :_messages as messages
        ",
            $comment_Map
        );
    }
}
