<?php
/**
 * vBulletin 5 Connect exporter tool.
 *
 * Add this 301 route to sidestep vB4->5 upgrade category redirects.
 *    Expression: forumdisplay\.php\?([0-9]+)-([a-zA-Z0-9-_]+)
 *    Target: /categories/$2
 *
 * @copyright Vanilla Forums Inc. 2014
 * @license Proprietary
 * @package VanillaPorter
 */

$Supported['vbulletin5'] = array('name' => 'vBulletin 5 Connect', 'prefix' => 'vb_');
$Supported['vbulletin5']['CommandLine'] = array(
    //'noexport' => array('Whether or not to skip the export.', 'Sx' => '::'),
);
$Supported['vbulletin5']['features'] = array(
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
    protected $SourceTables = array(
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
     * @param ExportModel $Ex
     */
    public function forumExport($Ex) {

        $CharacterSet = $Ex->getCharacterSet('nodes');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        $Ex->beginExport('', 'vBulletin 5 Connect');

        $this->exportBlobs(
            $this->param('files'),
            $this->param('avatars')
        );

        if ($this->param('noexport')) {
            $Ex->comment('Skipping the export.');
            $Ex->endExport();

            return;
        }

        $cdn = $this->param('cdn', '');


        // Grab all of the ranks.
        $Ranks = $Ex->get("select * from :_usertitle order by minposts desc", 'usertitleid');


        // Users
        $User_Map = array(
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
                'Filter' => function ($Value) use ($Ranks) {
                    // Look  up the posts in the ranks table.
                    foreach ($Ranks as $RankID => $Row) {
                        if ($Value >= $Row['minposts']) {
                            return $RankID;
                        }
                    }

                    return null;
                }
            )
        );

        // Use file avatar or the result of our blob export?
        if ($this->getConfig('usefileavatar')) {
            $User_Map['filephoto'] = 'Photo';
        } else {
            $User_Map['customphoto'] = 'Photo';
        }

        // vBulletin 5.1 changes the hash to crypt(md5(password), hash).
        // Switches from password & salt to token (and scheme & secret).
        // The scheme appears to be crypt()'s default and secret looks uselessly redundant.
        if ($Ex->exists('user', 'token') !== true) {
            $PasswordSQL = "concat(`password`, salt) as password2, 'vbulletin' as HashMethod,";
        } else {
            // vB 5.1 already concats the salt to the password as token, BUT ADDS A SPACE OF COURSE.
            $PasswordSQL = "replace(token, ' ', '') as password2, case when scheme = 'legacy' then 'vbulletin' else 'vbulletin5' end as HashMethod,";
        }

        $Ex->exportTable('User', "
            select
                u.*,
                ipaddress as ipaddress2,
                $PasswordSQL
                DATE_FORMAT(birthday_search,GET_FORMAT(DATE,'ISO')) as DateOfBirth,
                FROM_UNIXTIME(joindate) as DateFirstVisit,
                FROM_UNIXTIME(lastvisit) as DateLastActive,
                FROM_UNIXTIME(joindate) as DateInserted,
                FROM_UNIXTIME(lastactivity) as DateUpdated,
                case when avatarrevision > 0 then concat('$cdn', 'userpics/avatar', u.userid, '_', avatarrevision, '.gif')
                    when av.avatarpath is not null then av.avatarpath
                    else null
                end as filephoto,
                {$this->AvatarSelect},
                case when ub.userid is not null then 1 else 0 end as Banned
            from :_user u
                left join :_customavatar a on u.userid = a.userid
                left join :_avatar av on u.avatarid = av.avatarid
                left join :_userban ub
                    on u.userid = ub.userid
                    and ub.liftdate <= now()
         ;", $User_Map);  // ":_" will be replace by database prefix
        //ipdata - contains all IP records for user actions: view,visit,register,logon,logoff


        // Roles
        $Role_Map = array(
            'usergroupid' => 'RoleID',
            'title' => 'Name',
            'description' => 'Description'
        );
        $Ex->exportTable('Role', 'select * from :_usergroup', $Role_Map);


        // UserRoles
        $UserRole_Map = array(
            'userid' => 'UserID',
            'usergroupid' => 'RoleID'
        );
        $Ex->query("CREATE TEMPORARY TABLE VbulletinRoles (userid INT UNSIGNED not null, usergroupid INT UNSIGNED not null)");
        # Put primary groups into tmp table
        $Ex->query("insert into VbulletinRoles (userid, usergroupid) select userid, usergroupid from :_user");
        # Put stupid CSV column into tmp table
        $SecondaryRoles = $Ex->query("select userid, usergroupid, membergroupids from :_user", true);
        if (is_resource($SecondaryRoles)) {
            while (($Row = @mysql_fetch_assoc($SecondaryRoles)) !== false) {
                if ($Row['membergroupids'] != '') {
                    $Groups = explode(',', $Row['membergroupids']);
                    foreach ($Groups as $GroupID) {
                        $Ex->query("insert into VbulletinRoles (userid, usergroupid) values({$Row['userid']},{$GroupID})",
                            true);
                    }
                }
            }
        }
        # Export from our tmp table and drop
        $Ex->exportTable('UserRole', 'select distinct userid, usergroupid from VbulletinRoles', $UserRole_Map);
        $Ex->query("DROP TABLE IF EXISTS VbulletinRoles");


        // Permissions.
        $Permissions_Map = array(
            'usergroupid' => 'RoleID',
            'title' => array('Column' => 'Garden.SignIn.Allow', 'Filter' => array($this, 'SignInPermission')),
            'genericpermissions' => array('Column' => 'GenericPermissions', 'type' => 'int'),
            'forumpermissions' => array('Column' => 'ForumPermissions', 'type' => 'int')
        );
        $this->addPermissionColumns(self::$Permissions, $Permissions_Map);
        $Ex->exportTable('Permission', 'select * from :_usergroup', $Permissions_Map);


        // UserMeta
        /*$Ex->Query("CREATE TEMPORARY TABLE VbulletinUserMeta (`UserID` INT not null ,`Name` VARCHAR( 255 ) not null ,`Value` text not null)");
        # Standard vB user data
        $UserFields = array('usertitle' => 'Title', 'homepage' => 'Website', 'skype' => 'Skype', 'styleid' => 'StyleID');
        foreach($UserFields as $Field => $InsertAs)
           $Ex->Query("insert into VbulletinUserMeta (UserID, Name, Value) select userid, 'Profile.$InsertAs', $Field from :_user where $Field != ''");
        # Dynamic vB user data (userfield)
        $ProfileFields = $Ex->Query("select varname, text from :_phrase where product='vbulletin' and fieldname='cprofilefield' and varname like 'field%_title'");
        if (is_resource($ProfileFields)) {
           $ProfileQueries = array();
           while ($Field = @mysql_fetch_assoc($ProfileFields)) {
              $Column = str_replace('_title', '', $Field['varname']);
              $Name = preg_replace('/[^a-zA-Z0-9_-\s]/', '', $Field['text']);
              $ProfileQueries[] = "insert into VbulletinUserMeta (UserID, Name, Value)
                 select userid, 'Profile.".$Name."', ".$Column." from :_userfield where ".$Column." != ''";
           }
           foreach ($ProfileQueries as $Query) {
              $Ex->Query($Query);
           }
        }*/


        // Ranks
        $Rank_Map = array(
            'usertitleid' => 'RankID',
            'title' => 'Name',
            'title2' => 'Label',
            'minposts' => array(
                'Column' => 'Attributes',
                'Filter' => function ($Value) {
                    $Result = array(
                        'Criteria' => array(
                            'CountPosts' => $Value
                        )
                    );

                    return serialize($Result);
                }
            ),
            'level' => array(
                'Column' => 'Level',
                'Filter' => function ($Value) {
                    static $Level = 1;

                    return $Level++;
                }
            )
        );
        $Ex->exportTable('Rank', "
            select
                ut.*,
                ut.title as title2,
                0 as level
            from :_usertitle ut
            order by ut.minposts
         ;", $Rank_Map);


        /// Signatures
        // usertextfields.signature

        // Ignore
        // usertextfields.ignorelist

        /// Notes

        /// Warnings

        /// Activity (Wall)


        // Category.
        $Channels = array();
        $CategoryIDs = array();
        $HomeID = 0;
        $PrivateMessagesID = 0;

        // Filter Channels down to Forum tree
        $ChannelResult = $Ex->query("
            select
                n.*
            from :_node n
                left join :_contenttype ct on n.contenttypeid = ct.contenttypeid
            where ct.class = 'Channel'
        ;");

        while ($Channel = mysql_fetch_array($ChannelResult)) {
            $Channels[$Channel['nodeid']] = $Channel;
            if ($Channel['title'] == 'Forum') {
                $HomeID = $Channel['nodeid'];
            }
            if ($Channel['title'] == 'Private Messages') {
                $PrivateMessagesID = $Channel['nodeid'];
            }
        }

        if (!$HomeID) {
            exit("Missing node 'Forum'");
        }

        // Go thru the category list 6 times to build a (up to) 6-deep hierarchy
        $CategoryIDs[] = $HomeID;
        for ($i = 0; $i < 6; $i++) {
            foreach ($Channels as $Channel) {
                if (in_array($Channel['nodeid'], $CategoryIDs)) {
                    continue;
                }
                if (in_array($Channel['parentid'], $CategoryIDs)) {
                    $CategoryIDs[] = $Channel['nodeid'];
                }
            }
        }
        // Drop 'Forum' from the tree
        if (($key = array_search($HomeID, $CategoryIDs)) !== false) {
            unset($CategoryIDs[$key]);
        }

        $Category_Map = array(
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
        $Ex->exportTable('Category', "
            select
                n.*,
                FROM_UNIXTIME(publishdate) as DateInserted,
                if(parentid={$HomeID},-1,parentid) as parentid
            from :_node n
            where nodeid in (" . implode(',', $CategoryIDs) . ")
        ;", $Category_Map);


        /// Permission
        //permission - nodeid,(user)groupid, and it gets worse from there.


        // Discussion.
        $Discussion_Map = array(
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
        $DiscussionQuery = "
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
                and parentid in (".implode(',', $CategoryIDs).")
        ;";

        // Polls need to be wrapped in a discussion so we are gonna need to postpone discussion creations
        if ($this->_getPollsCount()) {
            // NOTE: Only polls that are directly under a channel (discussion) will be exported.
            // Vanilla poll plugin does not support polls as comments.

            $Ex->query("drop table if exists vBulletinDiscussionTable;");

            // Create a temporary table to hold old discussions and to create new discussions for polls
            $Ex->query("
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
            $Ex->query("insert into vBulletinDiscussionTable $DiscussionQuery");

            $this->_generatePollsDiscussion();

            // Export discussions
            $Sql = "
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
            $Ex->exportTable('Discussion', $Sql, $Discussion_Map);

            // Export polls
            $this->_exportPolls();

            // Cleanup tmp table
            $Ex->query("drop table vBulletinDiscussionTable;");
        } else {
            $Ex->exportTable('Discussion', $DiscussionQuery, $Discussion_Map);
        }

        // UserDiscussion
        $UserDiscussion_Map = array(
            'discussionid' => 'DiscussionID',
            'userid' => 'InsertUserID',
        );
        // Should be able to inner join `discussionread` for DateLastViewed
        // but it's blank in my sample data so I don't trust it.
        $Ex->exportTable('UserDiscussion', "
            select
                s.*,
                1 as Bookmarked,
                NOW() as DateLastViewed
            from :_subscribediscussion s
        ;", $UserDiscussion_Map);


        // Comment.
        $Comment_Map = array(
            'nodeid' => 'CommentID',
            'rawtext' => 'Body',
            'userid' => 'InsertUserID',
            'parentid' => 'DiscussionID',
        );

        $Ex->exportTable('Comment', "
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
                and parentid not in (" . implode(',', $CategoryIDs) . ")
        ;", $Comment_Map);


        /// Drafts
        // autosavetext table

        $instance = $this;
        // Media
        $Media_Map = array(
            'nodeid' => 'MediaID',
            'filename' => 'Name',
            'extension' => array('Column' => 'Type', 'Filter' => array($this, 'BuildMimeType')),
            'Path2' => array('Column' => 'Path', 'Filter' => array($this, 'BuildMediaPath')),
            'ThumbPath2' => array(
                'Column' => 'ThumbPath',
                'Filter' => function($Value, $Field, $Row) use ($instance) {
                    $filteredData = $this->FilterThumbnailData($Value, $Field, $Row);

                    if ($filteredData) {
                        return $instance->BuildMediaPath($Value, $Field, $Row);
                    } else {
                        return null;
                    }
                }
            ),
            'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'FilterThumbnailData')),
            'width' => 'ImageWidth',
            'height' => 'ImageHeight',
            'filesize' => 'Size',
        );
        $Ex->exportTable('Media', "
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
                if(n2.parentid in (" . implode(',', $CategoryIDs) . "),'discussion','comment') as ForeignTable
            from :_attach a
                left join :_node n on n.nodeid = a.nodeid
                left join :_filedata f on f.filedataid = a.filedataid
                left join :_node n2 on n.parentid = n2.nodeid
            where a.visible = 1
        ;", $Media_Map);
        // left join :_contenttype c on n.contenttypeid = c.contenttypeid


        // Conversations.
        $Conversation_Map = array(
            'nodeid' => 'ConversationID',
            'userid' => 'InsertUserID',
            'totalcount' => 'CountMessages',
            'title' => 'Subject',
        );
        $Ex->exportTable('Conversation', "
            select
                n.*,
                n.nodeid as FirstMessageID,
                FROM_UNIXTIME(n.publishdate) as DateInserted
            from :_node n
                left join :_text t on t.nodeid = n.nodeid
            where parentid = $PrivateMessagesID
                and t.rawtext <> ''
        ;", $Conversation_Map);


        // Conversation Messages.
        $ConversationMessage_Map = array(
            'nodeid' => 'MessageID',
            'rawtext' => 'Body',
            'userid' => 'InsertUserID'
        );
        $Ex->exportTable('ConversationMessage', "
            select
                n.*,
                t.rawtext,
                'BBCode' as Format,
                if(n.parentid<>$PrivateMessagesID,n.parentid,n.nodeid) as ConversationID,
                FROM_UNIXTIME(n.publishdate) as DateInserted
            from :_node n
                left join :_contenttype c on n.contenttypeid = c.contenttypeid
                left join :_text t on t.nodeid = n.nodeid
            where c.class = 'PrivateMessage'
                and t.rawtext <> ''
        ;", $ConversationMessage_Map);


        // User Conversation.
        $UserConversation_Map = array(
            'userid' => 'UserID',
            'nodeid' => 'ConversationID',
            'deleted' => 'Deleted'
        );
        // would be nicer to do an intermediary table to sum s.msgread for uc.CountReadMessages
        $Ex->exportTable('UserConversation', "
            select
                s.*
            from :_sentto s
        ;", $UserConversation_Map);


        /// Groups
        // class='SocialGroup'
        // class='SocialGroupDiscussion'
        // class='SocialGroupMessage'


        $Ex->endExport();
    }

    /**
     * @return int Number of poll that can be exported by the porter.
     */
    protected function _getPollsCount() {
        $Count = 0;

        $Sql = "show tables like ':_poll';";
        $Result = $this->Ex->query($Sql, true);

        if (mysql_num_rows($Result) === 1) {
            $Sql = "
                select count(*) AS Count
                from :_poll as p
                    inner join :_node as n on n.nodeid = p.nodeid
                    inner join :_node as pn on pn.nodeid = n.parentid
                    inner join :_contenttype as ct on ct.contenttypeid = pn.contenttypeid
                where ct.class = 'Channel'
            ;";

            $Result = $this->Ex->query($Sql);
            if ($Row = mysql_fetch_assoc($Result)) {
                $Count = $Row['Count'];
            }
        }

        return $Count;
    }

    /**
     * Generate discussions for polls.
     */
    protected function _generatePollsDiscussion() {
        $Ex = $this->Ex;

        $PollsThatNeedWrappingQuery = "
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

        $Sql = "
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
            ) $PollsThatNeedWrappingQuery
        ";

        $Ex->query($Sql);
    }

    protected function _exportPolls() {
        $Ex = $this->Ex;
        $fp = $Ex->File;

        $Poll_Map = array(
            'nodeid' => 'PollID',
            'title' => 'Name',
            'discussionid' => 'DiscussionID',
            'anonymous' => 'Anonymous',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'userid' => 'InsertUserId',
        );
        $Ex->exportTable('Poll', "
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
        ;", $Poll_Map);

        $PollOption_Map = array(
            'polloptionid' => 'PollOptionID',
            'nodeid' => 'PollID',
            'title' => 'Body',
            'format' => 'Format',
            'sort' => 'Sort',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'userid' => 'InsertUserID',
        );
        $Sql = "
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
        $ExportStructure = $Ex->getExportStructure($PollOption_Map, 'PollOption', $PollOption_Map);
        $RevMappings = $Ex->flipMappings($PollOption_Map);

        $Ex->writeBeginTable($fp, 'PollOption', $ExportStructure);

        $Result = $Ex->query($Sql);
        $CurrentPollID = null;
        $CurrentSortID = 0;
        while ($Row = mysql_fetch_assoc($Result)) {

            if ($CurrentPollID !== $Row['nodeid']) {
                $CurrentPollID = $Row['nodeid'];
                $CurrentSortID = 0;
            }

            $Row['sort'] = ++$CurrentSortID;

            $Ex->writeRow($fp, $Row, $ExportStructure, $RevMappings);
        }
        $Ex->writeEndTable($fp);
        $Ex->comment("Exported Table: PollOption (".mysql_num_rows($Result)." rows)");
        mysql_free_result($Result);

        $PollVote_Map = array(
            'userid' => 'UserID',
            'polloptionid' => 'PollOptionID',
            'votedate' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate')
        );
        $Ex->exportTable('PollVote', "
            select
                pv.userid,
                pv.polloptionid,
                pv.votedate
            from :_pollvote pv
        ;", $PollVote_Map);
    }
}

?>
