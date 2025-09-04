<?php

/**
 * vBulletin 5 Connect exporter tool.
 *
 * Add this 301 route to sidestep vB4->5 upgrade category redirects.
 *    Expression: forumdisplay\.php\?([0-9]+)-([a-zA-Z0-9-_]+)
 *    Target: /categories/$2
 *
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Source;

use Porter\ExportModel;

class VBulletin5 extends VBulletin
{
    public const SUPPORTED = [
        'name' => 'vBulletin 5 Connect',
        'prefix' => 'vb_',
        'charset_table' => 'node',
        'options' => [
            'db-avatars' => [
                'Enables exporting avatars from the database.',
                'Sx' => '::',
                'Default' => false,
            ],
            'db-files' => [
                'Enables exporting attachments from database.',
                'Sx' => '::',
                'Default' => false,
            ],
        ],
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 1,
            'Roles' => 1,
            'Avatars' => 1,
            'PrivateMessages' => 1,
            'Signatures' => 0,
            'Attachments' => 1,
            'Bookmarks' => 1,
        ]
    ];

    /**
     * @var array Required tables => columns.
     */
    public $sourceTables = array(
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
    public function run($ex)
    {
        /*$this->doFileExport(
            $this->param('db-files'),
            $this->param('db-avatars')
        );
        if ($this->param('noexport')) {
            $ex->comment('Skipping the export.');
            return;
        }*/

        $cdn = ''; //$this->param('cdn', '');

        // Grab all of the ranks.
        $ranks = $ex->get("select * from :_usertitle order by minposts desc", 'usertitleid');

        $this->usersV5($ex, $ranks, $cdn);
        $this->rolesV5($ex);
        //$this->permissionsV5($ex);
        $this->ranksV5($ex);

        list($categoryIDs, $privateMessagesID) = $this->categoryV5($ex);

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
        $discussionQuery = "select
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
                and parentid in (" . implode(',', $categoryIDs) . ");";

        // Polls need to be wrapped in a discussion so we are gonna need to postpone discussion creations
        if ($this->getPollsCount($ex)) {
            // NOTE: Only polls that are directly under a channel (discussion) will be exported.
            // Vanilla poll plugin does not support polls as comments.

            $ex->query("drop table if exists vBulletinDiscussionTable;");

            // Create a temporary table to hold old discussions and to create new discussions for polls
            $ex->query(
                "create table `vBulletinDiscussionTable` (
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
                );"
            );
            $ex->query("insert into vBulletinDiscussionTable $discussionQuery");

            $this->generatePollsDiscussion($ex);

            // Export discussions
            $sql = "select
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
                from vBulletinDiscussionTable;";
            $ex->export('Discussion', $sql, $discussion_Map);

            // Export polls
            $this->pollsV5($ex);

            // Cleanup tmp table
            $ex->query("drop table vBulletinDiscussionTable;");
        } else {
            $ex->export('Discussion', $discussionQuery, $discussion_Map);
        }

        // UserDiscussion
        $userDiscussion_Map = array(
            'discussionid' => 'DiscussionID',
            'userid' => 'InsertUserID',
        );
        // Should be able to inner join `discussionread` for DateLastViewed
        // but it's blank in my sample data so I don't trust it.
        $ex->export(
            'UserDiscussion',
            "select s.*,
                    1 as Bookmarked,
                    NOW() as DateLastViewed
                from :_subscribediscussion s;",
            $userDiscussion_Map
        );

        $this->commentsV5($ex, $categoryIDs);
        $this->attachmentsV5($ex, $categoryIDs);
        $this->conversationsV5($ex, $privateMessagesID);
    }

    /**
     * @return int Number of poll that can be exported by the porter.
     */
    protected function getPollsCount($ex)
    {
        $count = 0;

        $sql = "show tables like ':_poll';";
        $result = $ex->query($sql, true);

        if ($result->nextResultRow()) {
            $sql = "select count(*) AS Count
                from :_poll as p
                    inner join :_node as n on n.nodeid = p.nodeid
                    inner join :_node as pn on pn.nodeid = n.parentid
                    inner join :_contenttype as ct on ct.contenttypeid = pn.contenttypeid
                where ct.class = 'Channel';";

            $result = $ex->query($sql);
            if ($row = $result->nextResultRow()) {
                $count = $row['Count'];
            }
        }

        return $count;
    }

    /**
     * Generate discussions for polls.
     */
    protected function generatePollsDiscussion($ex)
    {
        $pollsThatNeedWrappingQuery = "select
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
            where ct.class = 'Channel';";

        $sql = "insert into vBulletinDiscussionTable(
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
            ) $pollsThatNeedWrappingQuery";

        $ex->query($sql);
    }

    /**
     * @param ExportModel $ex
     */
    protected function pollsV5(ExportModel $ex)
    {
        //$fp = $ex->file;

        $poll_Map = array(
            'nodeid' => 'PollID',
            'title' => 'Name',
            'discussionid' => 'DiscussionID',
            'anonymous' => 'Anonymous',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'userid' => 'InsertUserId',
        );
        $ex->export(
            'Poll',
            "select
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
                    /* this join only exports polls that could be wrapped in a discussion */
                    inner join vBulletinDiscussionTable as vbdt on vbdt.PollID = p.nodeid;",
            $poll_Map
        );

        $pollOption_Map = array(
            'polloptionid' => 'PollOptionID',
            'nodeid' => 'PollID',
            'title' => 'Body',
            'format' => 'Format',
            'sort' => 'Sort',
            'created' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'userid' => 'InsertUserID',
        );
        $sql = "select
                po.polloptionid,
                po.nodeid,
                po.title,
                'BBCode' as format,
                null as Sort,
                n.created,
                n.userid
            from :_polloption as po
                left join :_node as n on n.nodeid = po.nodeid;";

        // We have to generate a sort order so let's do the exportation manually line by line....
        list($revMappings, $legacyFilter) = $ex->normalizeDataMap($pollOption_Map);
        //$exportStructure = $ex->porterStructure['PollOption'];
        //$exportStructure = getExportStructure($pollOption_Map, $ex->mapStructure['PollOption'], $pollOption_Map);
        //$revMappings = flipMappings($pollOption_Map);

        //$ex->writeBeginTable($fp, 'PollOption', $exportStructure);

        $result = $ex->query($sql);
        $currentPollID = null;
        $currentSortID = 0;
        $pollCount = 0;
        while ($row = $result->nextResultRow()) {
            if ($currentPollID !== $row['nodeid']) {
                $currentPollID = $row['nodeid'];
                $currentSortID = 0;
            }

            $row['sort'] = ++$currentSortID;

            //$ex->writeRow($fp, $row, $exportStructure, $revMappings, $legacyFilter);
            $pollCount++;
        }
        //$ex->writeEndTable($fp);
        $ex->comment("Exported Table: PollOption (" . $pollCount . " rows)");

        $pollVote_Map = array(
            'userid' => 'UserID',
            'polloptionid' => 'PollOptionID',
            'votedate' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate')
        );
        $ex->export(
            'PollVote',
            "select
                    pv.userid,
                    pv.polloptionid,
                    pv.votedate
                from :_pollvote pv;",
            $pollVote_Map
        );
    }

    /**
     * @param ExportModel $ex
     * @param array $ranks
     * @param string $cdn
     */
    public function usersV5(ExportModel $ex, array $ranks, $cdn): void
    {
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
        if ($this->getConfig($ex, 'usefileavatar')) {
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
            $passwordSQL = "replace(token, ' ', '') as password2,
                case when scheme = 'legacy' then 'vbulletin' else 'vbulletin5' end as HashMethod,";
        }

        $ex->export(
            'User',
            "select u.*,
                    ipaddress as ipaddress2,
                    $passwordSQL
                    DATE_FORMAT(birthday_search,GET_FORMAT(DATE,'ISO')) as DateOfBirth,
                    FROM_UNIXTIME(joindate) as DateFirstVisit,
                    FROM_UNIXTIME(lastvisit) as DateLastActive,
                    FROM_UNIXTIME(joindate) as DateInserted,
                    FROM_UNIXTIME(lastactivity) as DateUpdated,
                    case when avatarrevision > 0 then
                        concat('$cdn', 'userpics/avatar', u.userid, '_', avatarrevision, '.gif')
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
                        and ub.liftdate <= now();",
            $user_Map
        );
        //ipdata - contains all IP records for user actions: view,visit,register,logon,logoff
    }

    /**
     * @param ExportModel $ex
     */
    public function rolesV5(ExportModel $ex)
    {
        $role_Map = array(
            'usergroupid' => 'RoleID',
            'title' => 'Name',
            'description' => 'Description'
        );
        $ex->export('Role', 'select * from :_usergroup', $role_Map);

        // UserRoles
        $userRole_Map = array(
            'userid' => 'UserID',
            'usergroupid' => 'RoleID'
        );
        $ex->query("drop table if exists VbulletinRoles");
        $ex->query("CREATE TABLE VbulletinRoles (userid INT UNSIGNED not null, usergroupid INT UNSIGNED not null)");
        // Put primary groups into tmp table
        $ex->query("insert into VbulletinRoles (userid, usergroupid) select userid, usergroupid from :_user");
        // Put stupid CSV column into tmp table
        $secondaryRoles = $ex->query("select userid, usergroupid, membergroupids from :_user");
        if (is_object($secondaryRoles)) {
            while (($row = $secondaryRoles->nextResultRow()) !== false) {
                if ($row['membergroupids'] != '') {
                    $groups = explode(',', $row['membergroupids']);
                    foreach ($groups as $groupID) {
                        $ex->query(
                            "insert into VbulletinRoles (userid, usergroupid) values({$row['userid']},{$groupID})"
                        );
                    }
                }
            }
        }
        // Export from our tmp table and drop
        $ex->export('UserRole', 'select distinct userid, usergroupid from VbulletinRoles', $userRole_Map);
        $ex->query("DROP TABLE IF EXISTS VbulletinRoles");
    }

    /**
     * @param ExportModel $ex
     */
    public function permissionsV5(ExportModel $ex): void
    {
        $permissions_Map = array(
            'usergroupid' => 'RoleID',
            'title' => array('Column' => 'Garden.SignIn.Allow', 'Filter' => array($this, 'signInPermission')),
            'genericpermissions' => array('Column' => 'GenericPermissions', 'type' => 'int'),
            'forumpermissions' => array('Column' => 'ForumPermissions', 'type' => 'int')
        );
        $this->addPermissionColumns(self::$permissions, $permissions_Map);
        $ex->export('Permission', 'select * from :_usergroup', $permissions_Map);
    }

    /**
     * @param ExportModel $ex
     * @return array|void
     */
    public function ranksV5(ExportModel $ex)
    {
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
        $ex->export(
            'Rank',
            "select ut.*,
                    ut.title as title2,
                    0 as level
                from :_usertitle ut
                order by ut.minposts;",
            $rank_Map
        );
    }

    /**
     * @param ExportModel $ex
     * @return array|void
     */
    public function categoryV5(ExportModel $ex)
    {
        $channels = array();
        $categoryIDs = array();
        $homeID = 0;
        $privateMessagesID = 0;

        // Filter Channels down to Forum tree
        $channelResult = $ex->query(
            "select n.*
                from :_node n
                    left join :_contenttype ct on n.contenttypeid = ct.contenttypeid
                where ct.class = 'Channel';"
        );

        while ($channel = $channelResult->nextResultRow()) {
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
        $ex->export(
            'Category',
            "select n.*,
                    FROM_UNIXTIME(publishdate) as DateInserted,
                    if(parentid={$homeID},-1,parentid) as parentid
                from :_node n
                where nodeid in (" . implode(',', $categoryIDs) . ");",
            $category_Map
        );
        return array($categoryIDs, $privateMessagesID);
    }

    /**
     * @param ExportModel $ex
     * @param mixed $categoryIDs
     */
    public function commentsV5(ExportModel $ex, $categoryIDs): void
    {
        // Detect inner comments (Can happen if a plugin is used)
        $innerCommentQuery = "select
                node.nodeid,
                nodePP.nodeid as parentid,
                node.userid,
                t.rawtext,
                'BBCode' as Format,
                FROM_UNIXTIME(node.publishdate) as DateInserted
            from :_node as node
                inner join :_contenttype as ct on ct.contenttypeid = node.contenttypeid
                    and ct.class = 'Text' /*Inner Comment*/
                inner join :_node as nodeP on nodeP.nodeid = node.parentid
                inner join :_contenttype as ctP on ctP.contenttypeid = nodeP.contenttypeid
                    and ctP.class = 'Text'/*Comment*/
                inner join :_node as nodePP on nodePP.nodeid = nodeP.parentid
                inner join :_contenttype as ctPP on ctPP.contenttypeid = nodePP.contenttypeid
                    and ctPP.class = 'Text'/*Discussion*/
                inner join :_node as nodePPP on nodePPP.nodeid = nodePP.parentid
                inner join :_contenttype as ctPPP on ctPPP.contenttypeid = nodePPP.contenttypeid
                    and ctPPP.class = 'Channel'/*Category*/
                left join :_text t on t.nodeid = node.nodeid
            where node.showpublished = 1";
        $result = $ex->query($innerCommentQuery . ' limit 1');

        $innerCommentSQLFix = null;
        if ($result->nextResultRow()) {
            $ex->query(
                "create table `vBulletinInnerCommentTable` (
                    `nodeid` int(10) unsigned not null,
                    `parentid` int(11) not null,
                    `userid` int(10) unsigned default null,
                    `rawtext` mediumtext,
                    `Format` varchar(10) not null,
                    `DateInserted` datetime not null,
                    primary key (`nodeid`)
                );"
            );
            $ex->query("insert into vBulletinInnerCommentTable $innerCommentQuery");

            $innerCommentSQLFix = "
                and n.nodeid not in (select nodeid from vBulletinInnerCommentTable)
            union all
            select * from vBulletinInnerCommentTable
            ";
        }

        $comment_Map = array(
            'nodeid' => 'CommentID',
            'rawtext' => 'Body',
            'userid' => 'InsertUserID',
            'parentid' => 'DiscussionID',
        );

        $ex->export(
            'Comment',
            "select
                    n.nodeid,
                    n.parentid,
                    n.userid,
                    t.rawtext,
                    'BBCode' as Format,
                    FROM_UNIXTIME(publishdate) as DateInserted
                from :_node n
                    left join :_contenttype c on n.contenttypeid = c.contenttypeid
                    left join :_text t on t.nodeid = n.nodeid
                where c.class = 'Text'
                    and n.showpublished = 1
                    and parentid not in (" . implode(',', $categoryIDs) . ")
                    $innerCommentSQLFix",
            $comment_Map
        );

        if ($innerCommentSQLFix !== null) {
            $ex->query("drop table if exists vBulletinInnerCommentTable");
        }
    }

    /**
     * @param ExportModel $ex
     * @param mixed $categoryIDs
     */
    public function attachmentsV5(ExportModel $ex, $categoryIDs)
    {
        $instance = $this;
        $media_Map = array(
            'nodeid' => 'MediaID',
            'filename' => 'Name',
            'extension' => array('Column' => 'Type', 'Filter' => array($this, 'buildMimeType')),
            'Path2' => array('Column' => 'Path', 'Filter' => array($this, 'buildMediaPath')),
            'ThumbPath2' => array(
                'Column' => 'ThumbPath',
                'Filter' => function ($value, $field, $row) use ($instance) {
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
        $ex->export(
            'Media',
            "select a.*,
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
                where a.visible = 1;",
            $media_Map
        );
        // left join :_contenttype c on n.contenttypeid = c.contenttypeid
    }

    /**
     * @param ExportModel $ex
     * @param mixed $privateMessagesID
     */
    public function conversationsV5(ExportModel $ex, $privateMessagesID): void
    {
        $conversation_Map = array(
            'nodeid' => 'ConversationID',
            'userid' => 'InsertUserID',
            'totalcount' => 'CountMessages',
            'title' => 'Subject',
        );
        $ex->export(
            'Conversation',
            "select n.*,
                    n.nodeid as FirstMessageID,
                    FROM_UNIXTIME(n.publishdate) as DateInserted
                from :_node n
                    left join :_text t on t.nodeid = n.nodeid
                where parentid = $privateMessagesID
                    and t.rawtext <> '';",
            $conversation_Map
        );

        // Conversation Messages.
        $conversationMessage_Map = array(
            'nodeid' => 'MessageID',
            'rawtext' => 'Body',
            'userid' => 'InsertUserID'
        );
        $ex->export(
            'ConversationMessage',
            "select n.*,
                    t.rawtext,
                    'BBCode' as Format,
                    if(n.parentid<>$privateMessagesID,n.parentid,n.nodeid) as ConversationID,
                    FROM_UNIXTIME(n.publishdate) as DateInserted
                from :_node n
                    left join :_contenttype c on n.contenttypeid = c.contenttypeid
                    left join :_text t on t.nodeid = n.nodeid
                where c.class = 'PrivateMessage'
                    and t.rawtext <> '';",
            $conversationMessage_Map
        );

        // User Conversation.
        $userConversation_Map = array(
            'userid' => 'UserID',
            'nodeid' => 'ConversationID',
            'deleted' => 'Deleted'
        );
        // would be nicer to do an intermediary table to sum s.msgread for uc.CountReadMessages
        $ex->export(
            'UserConversation',
            "select s.* from :_sentto s ;",
            $userConversation_Map
        );
    }
}
