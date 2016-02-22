<?php
/**
 * vBulletin 5 Connect exporter tool.
 *
 * Add this 301 route to sidestep vB4->5 upgrade category redirects.
 *    Expression: forumdisplay\.php\?([0-9]+)-([a-zA-Z0-9-_]+)
 *    Target: /categories/$2
 *
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['vbulletin5'] = array('name' => 'vBulletin 5 Connect', 'prefix' => 'vb_');
$supported['vbulletin5']['CommandLine'] = array(
    //'noexport' => array('Whether or not to skip the export.', 'Sx' => '::'),
);
$supported['vbulletin5']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'Attachments' => 1,
    'PrivateMessages' => 1,
    'Bookmarks' => 1,
    'Ranks' => 1,
    'Passwords' => 1,
    'Polls' => 1,
);

class VBulletin5 extends VBulletin {
    /** @var array Required tables => columns. */
    protected $sourceTables = array(
        'contenttype' => array('contenttypeid', 'class'),
        'node' => array('nodeid', 'description', 'title', 'description', 'userid', 'publishdate'),
        'text' => array('nodeid', 'rawtext'),
        'user' => array(
            'userid',
            'username',
            'email',
            'referrerid',
            'timezoneoffset',
            'posts',
            'birthday_search',
            'joindate',
            'lastvisit',
            'lastactivity',
            'membergroupids',
            'usergroupid',
            'usertitle',
            'avatarid',
        ),
        'userfield' => array('userid'),
        'usergroup' => array('usergroupid', 'title', 'description'),
        'usertitle' => array(),
    );

    /**
     *
     * @param ExportModel $ex
     */
    public function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('nodes');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->beginExport('', 'vBulletin 5 Connect');

        $this->exportBlobs(
            $this->param('files'),
            $this->param('avatars')
        );

        if ($this->param('noexport')) {
            $ex->comment('Skipping the export.');
            $ex->endExport();

            return;
        }

        $cdn = $this->param('cdn', '');


        // Grab all of the ranks.
        $ranks = $ex->get("select * from :_usertitle order by minposts desc", 'usertitleid');


        // Users
        $user_Map = array(
            'userid' => 'UserID',
            'username' => 'Name',
            'password2' => 'Password',
            'email' => 'Email',
            'referrerid' => 'InviteUserID',
            'timezoneoffset' => 'HourOffset',
            'ipaddress' => 'LastIPAddress',
            'ipaddress2' => 'InsertIPAddress',
            'usertitle' => 'Title',
            'posts' => array(
                'Column' => 'RankID',
                'Filter' => function ($value) use ($ranks) {
                    // Look  up the posts in the ranks table.
                    foreach ($ranks as $rankID => $row) {
                        if ($value >= $row['minposts']) {
                            return $rankID;
                        }
                    }

                    return null;
                }
            )
        );

        // Use file avatar or the result of our blob export?
        if ($this->getConfig('usefileavatar')) {
            $user_Map['filephoto'] = 'Photo';
        } else {
            $user_Map['customphoto'] = 'Photo';
        }

        // vBulletin 5.1 changes the hash to crypt(md5(password), hash).
        // Switches from password & salt to token (and scheme & secret).
        // The scheme appears to be crypt()'s default and secret looks uselessly redundant.
        if ($ex->exists('user', 'token') !== true) {
            $passwordSQL = "concat(`password`, salt) as password2, 'vbulletin' as HashMethod,";
        } else {
            // vB 5.1 already concats the salt to the password as token, BUT ADDS A SPACE OF COURSE.
            $passwordSQL = "replace(token, ' ', '') as password2, case when scheme = 'legacy' then 'vbulletin' else 'vbulletin5' end as HashMethod,";
        }

        $ex->exportTable('User', "
            select
                u.*,
                ipaddress as ipaddress2,
                $passwordSQL
                DATE_FORMAT(birthday_search,GET_FORMAT(DATE,'ISO')) as DateOfBirth,
                FROM_UNIXTIME(joindate) as DateFirstVisit,
                FROM_UNIXTIME(lastvisit) as DateLastActive,
                FROM_UNIXTIME(joindate) as DateInserted,
                FROM_UNIXTIME(lastactivity) as DateUpdated,
                case when avatarrevision > 0 then concat('$cdn', 'userpics/avatar', u.userid, '_', avatarrevision, '.gif')
                    when av.avatarpath is not null then av.avatarpath
                    else null
                end as filephoto,
                {$this->avatarSelect},
                case when ub.userid is not null then 1 else 0 end as Banned
            from :_user u
                left join :_customavatar a on u.userid = a.userid
                left join :_avatar av on u.avatarid = av.avatarid
                left join :_userban ub
                    on u.userid = ub.userid
                    and ub.liftdate <= now()
         ;", $user_Map);  // ":_" will be replaced by database prefix
        //ipdata - contains all IP records for user actions: view,visit,register,logon,logoff


        // Roles
        $role_Map = array(
            'usergroupid' => 'RoleID',
            'title' => 'Name',
            'description' => 'Description'
        );
        $ex->exportTable('Role', 'select * from :_usergroup', $role_Map);


        // UserRoles
        $userRole_Map = array(
            'userid' => 'UserID',
            'usergroupid' => 'RoleID'
        );
        $ex->query("CREATE TEMPORARY TABLE VbulletinRoles (userid INT UNSIGNED not null, usergroupid INT UNSIGNED not null)");
        # Put primary groups into tmp table
        $ex->query("insert into VbulletinRoles (userid, usergroupid) select userid, usergroupid from :_user");
        # Put stupid CSV column into tmp table
        $secondaryRoles = $ex->query("select userid, usergroupid, membergroupids from :_user", true);
        if (is_resource($secondaryRoles)) {
            while (($row = @mysql_fetch_assoc($secondaryRoles)) !== false) {
                if ($row['membergroupids'] != '') {
                    $groups = explode(',', $row['membergroupids']);
                    foreach ($groups as $groupID) {
                        $ex->query("insert into VbulletinRoles (userid, usergroupid) values({$row['userid']},{$groupID})",
                            true);
                    }
                }
            }
        }
        # Export from our tmp table and drop
        $ex->exportTable('UserRole', 'select distinct userid, usergroupid from VbulletinRoles', $userRole_Map);
        $ex->query("DROP TABLE IF EXISTS VbulletinRoles");


        // Permissions.
        $permissions_Map = array(
            'usergroupid' => 'RoleID',
            'title' => array('Column' => 'Garden.SignIn.Allow', 'Filter' => array($this, 'signInPermission')),
            'genericpermissions' => array('Column' => 'GenericPermissions', 'type' => 'int'),
            'forumpermissions' => array('Column' => 'ForumPermissions', 'type' => 'int')
        );
        $this->addPermissionColumns(self::$permissions, $permissions_Map);
        $ex->exportTable('Permission', 'select * from :_usergroup', $permissions_Map);


        // UserMeta
        /*$ex->Query("CREATE TEMPORARY TABLE VbulletinUserMeta (`UserID` INT not null ,`Name` VARCHAR( 255 ) not null ,`Value` text not null)");
        # Standard vB user data
        $UserFields = array('usertitle' => 'Title', 'homepage' => 'Website', 'skype' => 'Skype', 'styleid' => 'StyleID');
        foreach($UserFields as $Field => $InsertAs)
           $ex->Query("insert into VbulletinUserMeta (UserID, Name, Value) select userid, 'Profile.$InsertAs', $Field from :_user where $Field != ''");
        # Dynamic vB user data (userfield)
        $ProfileFields = $ex->Query("select varname, text from :_phrase where product='vbulletin' and fieldname='cprofilefield' and varname like 'field%_title'");
        if (is_resource($ProfileFields)) {
           $ProfileQueries = array();
           while ($Field = @mysql_fetch_assoc($ProfileFields)) {
              $Column = str_replace('_title', '', $Field['varname']);
              $Name = preg_replace('/[^a-zA-Z0-9_-\s]/', '', $Field['text']);
              $ProfileQueries[] = "insert into VbulletinUserMeta (UserID, Name, Value)
                 select userid, 'Profile.".$Name."', ".$Column." from :_userfield where ".$Column." != ''";
           }
           foreach ($ProfileQueries as $Query) {
              $ex->Query($Query);
           }
        }*/


        // Ranks
        $rank_Map = array(
            'usertitleid' => 'RankID',
            'title' => 'Name',
            'title2' => 'Label',
            'minposts' => array(
                'Column' => 'Attributes',
                'Filter' => function ($value) {
                    $result = array(
                        'Criteria' => array(
                            'CountPosts' => $value
                        )
                    );

                    return serialize($result);
                }
            ),
            'level' => array(
                'Column' => 'Level',
                'Filter' => function ($value) {
                    static $level = 1;

                    return $level++;
                }
            )
        );
        $ex->exportTable('Rank', "
            select
                ut.*,
                ut.title as title2,
                0 as level
            from :_usertitle ut
            order by ut.minposts
         ;", $rank_Map);


        /// Signatures
        // usertextfields.signature

        // Ignore
        // usertextfields.ignorelist

        /// Notes

        /// Warnings

        /// Activity (Wall)


        // Category.
        $channels = array();
        $categoryIDs = array();
        $homeID = 0;
        $privateMessagesID = 0;

        // Filter Channels down to Forum tree
        $channelResult = $ex->query("
            select
                n.*
            from :_node n
                left join :_contenttype ct on n.contenttypeid = ct.contenttypeid
            where ct.class = 'Channel'
        ;");

        while ($channel = mysql_fetch_array($channelResult)) {
            $channels[$channel['nodeid']] = $channel;
            if ($channel['title'] == 'Forum') {
                $homeID = $channel['nodeid'];
            }
            if ($channel['title'] == 'Private Messages') {
                $privateMessagesID = $channel['nodeid'];
            }
        }

        if (!$homeID) {
            exit("Missing node 'Forum'");
        }

        // Go through the category list 6 times to build a (up to) 6-deep hierarchy
        $categoryIDs[] = $homeID;
        for ($i = 0; $i < 6; $i++) {
            foreach ($channels as $channel) {
                if (in_array($channel['nodeid'], $categoryIDs)) {
                    continue;
                }
                if (in_array($channel['parentid'], $categoryIDs)) {
                    $categoryIDs[] = $channel['nodeid'];
                }
            }
        }
        // Drop 'Forum' from the tree
        if (($key = array_search($homeID, $categoryIDs)) !== false) {
            unset($categoryIDs[$key]);
        }

        $category_Map = array(
            'nodeid' => 'CategoryID',
            'title' => 'Name',
            'description' => 'Description',
            'userid' => 'InsertUserID',
            'parentid' => 'ParentCategoryID',
            'urlident' => 'UrlCode',
            'displayorder' => array('Column' => 'Sort', 'Type' => 'int'),
            'lastcontentid' => 'LastDiscussionID',
            'textcount' => 'CountComments', // ???
            'totalcount' => 'CountDiscussions', // ???
        );

        // Categories are Channels that were found in the Forum tree
        // If parent was 'Forum' set the parent to Root instead (-1)
        $ex->exportTable('Category', "
            select
                n.*,
                FROM_UNIXTIME(publishdate) as DateInserted,
                if(parentid={$homeID},-1,parentid) as parentid
            from :_node n
            where nodeid in (" . implode(',', $categoryIDs) . ")
        ;", $category_Map);


        /// Permission
        //permission - nodeid,(user)groupid, and it gets worse from there.


        // Discussion.
        $discussion_Map = array(
            'nodeid' => 'DiscussionID',
            'type' => 'Type',
            'title' => 'Name',
            'userid' => 'InsertUserID',
            'rawtext' => 'Body',
            'parentid' => 'CategoryID',
            'lastcontentid' => 'LastCommentID',
            'lastauthorid' => 'LastCommentUserID',
            // htmlstate - on,off,on_nl2br
            // infraction
            // attach
            // reportnodeid
        );
        $discussionQuery = "
            select
                n.nodeid,
                null as type,
                n.title,
                n.userid,
                t.rawtext,
                n.parentid,
                n.lastcontentid,
                n.lastauthorid,
                'BBCode' as Format,
                FROM_UNIXTIME(publishdate) as DateInserted,
                v.count as CountViews,
                convert(ABS(n.open-1),char(1)) as Closed,
                if(convert(n.sticky,char(1))>0,2,0) as Announce,
                null as PollID
            from :_node n
                left join :_contenttype ct on n.contenttypeid = ct.contenttypeid
                left join :_nodeview v on v.nodeid = n.nodeid
                left join :_text t on t.nodeid = n.nodeid
            where ct.class = 'Text'
                and n.showpublished = 1
                and parentid in (".implode(',', $categoryIDs).")
        ;";

        // Polls need to be wrapped in a discussion so we are gonna need to postpone discussion creations
        if ($this->_getPollsCount()) {
            // NOTE: Only polls that are directly under a channel (discussion) will be exported.
            // Vanilla poll plugin does not support polls as comments.

            $ex->query("drop table if exists vBulletinDiscussionTable;");

            // Create a temporary table to hold old discussions and to create new discussions for polls
            $ex->query("
                create table `vBulletinDiscussionTable` (
                    `nodeid` int(10) unsigned not null AUTO_INCREMENT,
                    `type` varchar(10) default null,
                    `title` varchar(255) default null,
                    `userid` int(10) unsigned default null,
                    `rawtext` mediumtext,
                    `parentid` int(11) not null,
                    `lastcontentid` int(11) not null default '0',
                    `lastauthorid` int(10) unsigned not null default '0',
                    `Format` varchar(10) not null,
                    `DateInserted` datetime not null,
                    `CountViews` int(11) not null default '1',
                    `Closed` tinyint(4) not null default '0',
                    `Announce` tinyint(4) not null default '0',
                    `PollID` int(10) unsigned, /* used to create poll->discussion mapping */
                    primary key (`nodeid`)
                )
            ;");
            $ex->query("insert into vBulletinDiscussionTable $discussionQuery");

            $this->_generatePollsDiscussion();

            // Export discussions
            $sql = "
                select
                    nodeid,
                    type,
                    title,
                    userid,
                    rawtext,
                    parentid,
                    lastcontentid,
                    lastauthorid,
                    Format,
                    DateInserted,
                    CountViews,
                    Closed,
                    Announce
                from vBulletinDiscussionTable
            ;";
            $ex->exportTable('Discussion', $sql, $discussion_Map);

            // Export polls
            $this->_exportPolls();

            // Cleanup tmp table
            $ex->query("drop table vBulletinDiscussionTable;");
        } else {
            $ex->exportTable('Discussion', $discussionQuery, $discussion_Map);
        }

        // UserDiscussion
        $userDiscussion_Map = array(
            'discussionid' => 'DiscussionID',
            'userid' => 'InsertUserID',
        );
        // Should be able to inner join `discussionread` for DateLastViewed
        // but it's blank in my sample data so I don't trust it.
        $ex->exportTable('UserDiscussion', "
            select
                s.*,
                1 as Bookmarked,
                NOW() as DateLastViewed
            from :_subscribediscussion s
        ;", $userDiscussion_Map);


        // Comment.
        $comment_Map = array(
            'nodeid' => 'CommentID',
            'rawtext' => 'Body',
            'userid' => 'InsertUserID',
            'parentid' => 'DiscussionID',
        );

        $ex->exportTable('Comment', "
            select
                n.*,
                t.rawtext,
                'BBCode' as Format,
                FROM_UNIXTIME(publishdate) as DateInserted
            from :_node n
                left join :_contenttype c on n.contenttypeid = c.contenttypeid
                left join :_text t on t.nodeid = n.nodeid
            where c.class = 'Text'
                and n.showpublished = 1
                and parentid not in (".implode(',', $categoryIDs).")
                and parentid in (select t2.nodeid from :_text as t2) /* exclude inner comments */
        ;", $comment_Map);

        // Detect inner comments
        $result = $ex->query("
            select
                n.nodeid
            from :_node as n
                left join :_contenttype c on n.contenttypeid = c.contenttypeid
                left join :_text t on t.nodeid = n.nodeid
            where
                parentid not in (".implode(',', $categoryIDs).")
                and parentid in (select t2.nodeid from :_text as t2)
            limit 1
        ", true);

        if (mysql_num_rows($result)) {
            $ex->comment('*** Inner comments detected but not imported.');
        }

        /// Drafts
        // autosavetext table

        $instance = $this;
        // Media
        $media_Map = array(
            'nodeid' => 'MediaID',
            'filename' => 'Name',
            'extension' => array('Column' => 'Type', 'Filter' => array($this, 'buildMimeType')),
            'Path2' => array('Column' => 'Path', 'Filter' => array($this, 'buildMediaPath')),
            'ThumbPath2' => array(
                'Column' => 'ThumbPath',
                'Filter' => function($value, $field, $row) use ($instance) {
                    $filteredData = $this->filterThumbnailData($value, $field, $row);

                    if ($filteredData) {
                        return $instance->buildMediaPath($value, $field, $row);
                    } else {
                        return null;
                    }
                }
            ),
            'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'filterThumbnailData')),
            'width' => 'ImageWidth',
            'height' => 'ImageHeight',
            'filesize' => 'Size',
        );
        $ex->exportTable('Media', "
            select
                a.*,
                filename as Path2,
                filename as ThumbPath2,
                128 as thumb_width,
                FROM_UNIXTIME(f.dateline) as DateInserted,
                f.userid as userid,
                f.userid as InsertUserID,
                if (f.width,f.width,1) as width,
                if (f.height,f.height,1) as height,
                n.parentid as ForeignID,
                f.extension,
                f.filesize,
                if(n2.parentid in (" . implode(',', $categoryIDs) . "),'discussion','comment') as ForeignTable
            from :_attach a
                left join :_node n on n.nodeid = a.nodeid
                left join :_filedata f on f.filedataid = a.filedataid
                left join :_node n2 on n.parentid = n2.nodeid
            where a.visible = 1
        ;", $media_Map);
        // left join :_contenttype c on n.contenttypeid = c.contenttypeid


        // Conversations.
        $conversation_Map = array(
            'nodeid' => 'ConversationID',
            'userid' => 'InsertUserID',
            'totalcount' => 'CountMessages',
            'title' => 'Subject',
        );
        $ex->exportTable('Conversation', "
            select
                n.*,
                n.nodeid as FirstMessageID,
                FROM_UNIXTIME(n.publishdate) as DateInserted
            from :_node n
                left join :_text t on t.nodeid = n.nodeid
            where parentid = $privateMessagesID
                and t.rawtext <> ''
        ;", $conversation_Map);


        // Conversation Messages.
        $conversationMessage_Map = array(
            'nodeid' => 'MessageID',
            'rawtext' => 'Body',
            'userid' => 'InsertUserID'
        );
        $ex->exportTable('ConversationMessage', "
            select
                n.*,
                t.rawtext,
                'BBCode' as Format,
                if(n.parentid<>$privateMessagesID,n.parentid,n.nodeid) as ConversationID,
                FROM_UNIXTIME(n.publishdate) as DateInserted
            from :_node n
                left join :_contenttype c on n.contenttypeid = c.contenttypeid
                left join :_text t on t.nodeid = n.nodeid
            where c.class = 'PrivateMessage'
                and t.rawtext <> ''
        ;", $conversationMessage_Map);


        // User Conversation.
        $userConversation_Map = array(
            'userid' => 'UserID',
            'nodeid' => 'ConversationID',
            'deleted' => 'Deleted'
        );
        // would be nicer to do an intermediary table to sum s.msgread for uc.CountReadMessages
        $ex->exportTable('UserConversation', "
            select
                s.*
            from :_sentto s
        ;", $userConversation_Map);


        /// Groups
        // class='SocialGroup'
        // class='SocialGroupDiscussion'
        // class='SocialGroupMessage'


        $ex->endExport();
    }

    /**
     * @return int Number of poll that can be exported by the porter.
     */
    protected function _getPollsCount() {
        $count = 0;

        $sql = "show tables like ':_poll';";
        $result = $this->ex->query($sql, true);

        if (mysql_num_rows($result) === 1) {
            $sql = "
                select count(*) AS Count
                from :_poll as p
                    inner join :_node as n on n.nodeid = p.nodeid
                    inner join :_node as pn on pn.nodeid = n.parentid
                    inner join :_contenttype as ct on ct.contenttypeid = pn.contenttypeid
                where ct.class = 'Channel'
            ;";

            $result = $this->ex->query($sql);
            if ($row = mysql_fetch_assoc($result)) {
                $count = $row['Count'];
            }
        }

        return $count;
    }

    /**
     * Generate discussions for polls.
     */
    protected function _generatePollsDiscussion() {
        $ex = $this->ex;

        $pollsThatNeedWrappingQuery = "
            select
                'poll' as type,
                n.title,
                n.userid,
                t.rawtext,
                n.parentid,
                n.lastcontentid,
                n.lastauthorid,
                'BBCode' as Format,
                FROM_UNIXTIME(n.publishdate) as DateInserted,
                v.count as CountViews,
                convert(ABS(n.open-1),char(1)) as Closed,
                if(convert(n.sticky,char(1))>0,2,0) as Announce,
                n.nodeid as PollID

            from :_poll as p
                inner join :_node as n on n.nodeid = p.nodeid
                inner join :_node as pn on pn.nodeid = n.parentid
                inner join :_contenttype as ct on ct.contenttypeid = pn.contenttypeid
                left join :_nodeview v on v.nodeid = n.nodeid
                left join :_text t on t.nodeid = n.nodeid
            where ct.class = 'Channel'
        ;";

        $sql = "
            insert into vBulletinDiscussionTable(
                /* `nodeid`, will be auto generated */
                `type`,
                `title`,
                `userid`,
                `rawtext`,
                `parentid`,
                `lastcontentid`,
                `lastauthorid`,
                `Format`,
                `DateInserted`,
                `CountViews`,
                `Closed`,
                `Announce`,
                `PollID`
            ) $pollsThatNeedWrappingQuery
        ";

        $ex->query($sql);
    }

    protected function _exportPolls() {
        $ex = $this->ex;
        $fp = $ex->file;

        $poll_Map = array(
            'nodeid' => 'PollID',
            'title' => 'Name',
            'discussionid' => 'DiscussionID',
            'anonymous' => 'Anonymous',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'userid' => 'InsertUserId',
        );
        $ex->exportTable('Poll', "
            select
                p.nodeid,
                n.title,
                vbdt.nodeid as discussionid,
                !p.public as anonymous,
                n.created,
                n.userid
            from :_poll as p
                inner join :_node as n on n.nodeid = p.nodeid
                inner join :_node as pn on pn.nodeid = n.parentid
                inner join :_contenttype as pct on pct.contenttypeid = pn.contenttypeid
                /* by inner joining on this table we are only exporting polls that could be wrapped in a discussion */
                inner join vBulletinDiscussionTable as vbdt on vbdt.PollID = p.nodeid
        ;", $poll_Map);

        $pollOption_Map = array(
            'polloptionid' => 'PollOptionID',
            'nodeid' => 'PollID',
            'title' => 'Body',
            'format' => 'Format',
            'sort' => 'Sort',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'userid' => 'InsertUserID',
        );
        $sql = "
            select
                po.polloptionid,
                po.nodeid,
                po.title,
                'BBCode' as format,
                null as Sort,
                n.created,
                n.userid
            from :_polloption as po
                left join :_node as n on n.nodeid = po.nodeid
        ;";

        // We have to generate a sort order so let's do the exportation manually line by line....
        $exportStructure = $ex->getExportStructure($pollOption_Map, 'PollOption', $pollOption_Map);
        $revMappings = $ex->flipMappings($pollOption_Map);

        $ex->writeBeginTable($fp, 'PollOption', $exportStructure);

        $result = $ex->query($sql);
        $currentPollID = null;
        $currentSortID = 0;
        while ($row = mysql_fetch_assoc($result)) {

            if ($currentPollID !== $row['nodeid']) {
                $currentPollID = $row['nodeid'];
                $currentSortID = 0;
            }

            $row['sort'] = ++$currentSortID;

            $ex->writeRow($fp, $row, $exportStructure, $revMappings);
        }
        $ex->writeEndTable($fp);
        $ex->comment("Exported Table: PollOption (".mysql_num_rows($result)." rows)");
        mysql_free_result($result);

        $pollVote_Map = array(
            'userid' => 'UserID',
            'polloptionid' => 'PollOptionID',
            'votedate' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate')
        );
        $ex->exportTable('PollVote', "
            select
                pv.userid,
                pv.polloptionid,
                pv.votedate
            from :_pollvote pv
        ;", $pollVote_Map);
    }
}

// Closing PHP tag required. (make.php)
?>
