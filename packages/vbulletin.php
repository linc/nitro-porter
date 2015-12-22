<?php
/**
 * vBulletin exporter tool.
 *
 * This will migrate all vBulletin data for 3.x and 4.x forums.
 * It migrates all attachments from 2.x and later.
 *
 * Supports the FileUpload, ProfileExtender, and Signature plugins.
 * All vBulletin data appropriate for those plugins will be prepared
 * and transferred.
 *
 * To export only 1 category, add 'forumid=#' parameter to the URL.
 * To extract avatars stored in database, add 'avatars=1' parameter to the URL.
 * To extract attachments stored in db, add 'attachments=1' parameter to the URL.
 * To extract all usermeta data (title, skype, custom profile fields, etc),
 *    add 'usermeta=1' parameter to the URL.
 * To stop the export after only extracting files, add 'noexport=1' param to the URL.
 *
 * TO MIGRATE FILES, BEFORE IMPORTING YOU MUST:
 * 1) Copy entire 'customavatars' folder into Vanilla's /upload folder.
 * 2) Copy entire 'attachments' folder into Vanilla's / upload folder.
 * 3) Make BOTH folders writable by the server.
 * 4) Enable the FileUpload plugin. (Media table must be present.)
 *
 * filepath - Command line option to fix / check files are on disk.  Files named .attach are renamed
 * to the proper name and missing files are reported in missing-files.txt.
 *
 * @copyright Vanilla Forums Inc. 2010
 * @author Matt Lincoln Russell lincoln@icrontic.com
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['vbulletin'] = array('name' => 'vBulletin 3 & 4', 'prefix' => 'vb_');
// Commented commands are still supported, if you really want to use them.
$Supported['vbulletin']['CommandLine'] = array(
    //'noexport' => array('Exports only the blobs.', 'Sx' => '::'),
    'mindate' => array('A date to import from. Like selective amnesia.'),
    //'forumid' => array('Only export 1 forum'),
    //'ipbanlist' => array('Export IP ban list, which is a terrible idea.'),
    'filepath' => array('Full path of file attachments to be renamed.', 'Sx' => '::')
);
$Supported['vbulletin']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'Attachments' => 1,
    'PrivateMessages' => 1,
    'Permissions' => 1,
    'UserWall' => 1,
    'UserNotes' => 1,
    'Bookmarks' => 1,
    'Passwords' => 1,
    'Signatures' => 1,
    'Ranks' => 1,
    'Polls' => 1,
);

/**
 * vBulletin-specific extension of generic ExportController.
 *
 * @package VanillaPorter
 */
class Vbulletin extends ExportController {
    /* @var string SQL fragment to build new path to attachments. */
    public $AttachSelect = "concat('/vbulletin/', left(f.filehash, 2), '/', f.filehash, '_', a.attachmentid,'.', f.extension) as Path";

    /* @var string SQL fragment to build new path to user photo. */
    public $AvatarSelect = "case
      when a.userid is not null then concat('customavatars/', a.userid % 100,'/avatar_', a.userid, right(a.filename, instr(reverse(a.filename), '.')))
      when av.avatarpath is not null then av.avatarpath
      else null
      end as customphoto";

    /* @var array Default permissions to map. */
    public static $Permissions = array(

        'genericpermissions' => array(
            1 => array('Garden.Profiles.View', 'Garden.Activity.View'),
            2 => 'Garden.Profiles.Edit',
            1024 => 'Plugins.Signatures.Edit'
        ),
        'forumpermissions' => array(
            1 => 'Vanilla.Discussions.View',
            16 => 'Vanilla.Discussions.Add',
            64 => 'Vanilla.Comments.Add',
            4096 => 'Plugins.Attachments.Download',
            8192 => 'Plugins.Attachments.Upload'
        ),
        'adminpermissions' => array(
            1 => array(
                'Garden.Moderation.Manage',
                'Vanilla.Discussions.Announce',
                'Vanilla.Discussions.Close',
                'Vanilla.Discussions.Delete',
                'Vanilla.Comments.Delete',
                'Vanilla.Comments.Edit',
                'Vanilla.Discussions.Edit',
                'Vanilla.Discussions.Sink',
                'Garden.Activity.Delete',
                'Garden.Users.Add',
                'Garden.Users.Edit',
                'Garden.Users.Approve',
                'Garden.Users.Delete',
                'Garden.Applicants.Manage'
            ),
            2 => array(
                'Garden.Settings.View',
                'Garden.Settings.Manage',
                'Garden.Messages.Manage',
                'Vanilla.Spam.Manage'
            )
//          4 => 'Garden.Settings.Manage',),
        ),
//      'wolpermissions' => array(
//          16 => 'Plugins.WhosOnline.ViewHidden')
    );

    public static $Permissions2 = array();

    /** @var array Required tables => columns. Commented values are optional. */
    protected $SourceTables = array(
        //'attachment'
        //'contenttype'
        //'customavatar'
        'deletionlog' => array('type', 'primaryid'),
        //'filedata'
        'forum' => array('forumid', 'description', 'displayorder', 'title', 'description', 'displayorder'),
        //'phrase' => array('varname','text','product','fieldname','varname'),
        //'pm'
        //'pmgroup'
        //'pmreceipt'
        //'pmtext'
        'post' => array('postid', 'threadid', 'pagetext', 'userid', 'dateline', 'visible'),
        //'setting'
        'subscribethread' => array('userid', 'threadid'),
        'thread' => array(
            'threadid',
            'forumid',
            'postuserid',
            'title',
            'open',
            'sticky',
            'dateline',
            'lastpost',
            'visible'
        ),
        //'threadread'
        'user' => array(
            'userid',
            'username',
            'password',
            'email',
            'referrerid',
            'timezoneoffset',
            'posts',
            'salt',
            'birthday_search',
            'joindate',
            'lastvisit',
            'lastactivity',
            'membergroupids',
            'usergroupid',
            'usertitle',
            'homepage',
            'aim',
            'icq',
            'yahoo',
            'msn',
            'skype',
            'styleid',
            'avatarid'
        ),
        //'userban'
        'userfield' => array('userid'),
        'usergroup' => array('usergroupid', 'title', 'description'),
        //'visitormessage'
    );

    /**
     * Export each table one at a time.
     *
     * @param ExportModel $Ex
     */
    protected function forumExport($Ex) {
        // Allow limited export of 1 category via ?forumid=ID
        $ForumID = $this->param('forumid');
        if ($ForumID) {
            $ForumWhere = ' and t.forumid ' . (strpos($ForumID, ', ') === false ? "= $ForumID" : "in ($ForumID)");
        } else {
            $ForumWhere = '';
        }

        $CharacterSet = $Ex->getCharacterSet('post');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        // Begin
        $Ex->beginExport('', 'vBulletin 3.* and 4.*');
        $this->exportBlobs(
            $this->param('files'),
            $this->param('avatars'),
            $ForumWhere
        );

        if ($this->param('noexport')) {
            $Ex->comment('Skipping the export.');
            $Ex->endExport();

            return;
        }
        // Check to see if there is a max date.
        $MinDate = $this->param('mindate');
        if ($MinDate) {
            $MinDate = strtotime($MinDate);
            $Ex->comment("Min topic date ($MinDate): " . date('c', $MinDate));
        }
        $Now = time();

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
            'usertitle' => array(
                'Column' => 'Title',
                'Filter' => function ($Value) {
                    return trim(strip_tags(str_replace('&nbsp;', ' ', $Value)));
                }
            ),
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

        $Ex->exportTable('User', "select u.*,
            ipaddress as ipaddress2,
            concat(`password`, salt) as password2,
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
            case when ub.userid is not null then 1 else 0 end as Banned,
            'vbulletin' as HashMethod
         from :_user u
         left join :_customavatar a
            on u.userid = a.userid
         left join :_avatar av
            on u.avatarid = av.avatarid
         left join :_userban ub
              on u.userid = ub.userid and ub.liftdate <= now() ",
            $User_Map);  // ":_" will be replace by database prefix

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
        $Ex->query("CREATE TEMPORARY TABLE VbulletinRoles (userid INT UNSIGNED NOT NULL, usergroupid INT UNSIGNED NOT NULL)");
        # Put primary groups into tmp table
        $Ex->query("insert into VbulletinRoles (userid, usergroupid) select userid, usergroupid from :_user");
        # Put stupid CSV column into tmp table
        $SecondaryRoles = $Ex->query("select userid, usergroupid, membergroupids from :_user", true);
        if (is_resource($SecondaryRoles)) {
            while (($Row = @mysql_fetch_assoc($SecondaryRoles)) !== false) {
                if ($Row['membergroupids'] != '') {
                    $Groups = explode(',', $Row['membergroupids']);
                    foreach ($Groups as $GroupID) {
                        if (!$GroupID) {
                            continue;
                        }
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
            'title' => array('Column' => 'Garden.SignIn.Allow', 'Filter' => array($this, 'signInPermission')),
            'genericpermissions' => array('Column' => 'GenericPermissions', 'type' => 'int'),
            'forumpermissions' => array('Column' => 'ForumPermissions', 'type' => 'int')
        );
        $this->addPermissionColumns(self::$Permissions, $Permissions_Map);
        $Ex->exportTable('Permission', 'select * from :_usergroup', $Permissions_Map);

//      $Ex->EndExport();
//      return;

        // UserMeta
        $Ex->query("CREATE TEMPORARY TABLE VbulletinUserMeta (`UserID` INT NOT NULL ,`Name` VARCHAR( 255 ) NOT NULL ,`Value` text NOT NULL)");
        # Standard vB user data
        $UserFields = array(
            'usertitle' => 'Title',
            'homepage' => 'Website',
            'skype' => 'Skype',
            'styleid' => 'StyleID'
        );
        foreach ($UserFields as $Field => $InsertAs) {
            $Ex->query("insert into VbulletinUserMeta (UserID, Name, Value) select userid, 'Profile.$InsertAs', $Field from :_user where $Field != ''");
        }
        # Dynamic vB user data (userfield)
        $ProfileFields = $Ex->query("select varname, text from :_phrase where product='vbulletin' and fieldname='cprofilefield' and varname like 'field%_title'");
        if (is_resource($ProfileFields)) {
            $ProfileQueries = array();
            while ($Field = @mysql_fetch_assoc($ProfileFields)) {
                $Column = str_replace('_title', '', $Field['varname']);
                $Name = preg_replace('/[^a-zA-Z0-9\s_-]/', '', $Field['text']);
                $ProfileQueries[] = "insert into VbulletinUserMeta (UserID, Name, Value)
               select userid, 'Profile." . $Name . "', " . $Column . " from :_userfield where " . $Column . " != ''";
            }
            foreach ($ProfileQueries as $Query) {
                $Ex->query($Query);
            }
        }


        // Signatures
        $Sql = "
         select
            userid as UserID,
            'Plugin.Signatures.Sig' as Name,
            signature as Value
         from :_usertextfield
         where nullif(signature, '') is not null

         union

         select
            userid,
            'Plugin.Signatures.Format',
            'BBCode'
         from :_usertextfield
         where nullif(signature, '') is not null";
        $Ex->exportTable('UserMeta', $Sql);


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
         select ut.*, ut.title as title2, 0 as level
         from :_usertitle ut
         order by ut.minposts", $Rank_Map);


        // Categories
        $Category_Map = array(
            'forumid' => 'CategoryID',
            'description' => 'Description',
            'Name2' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'displayorder' => array('Column' => 'Sort', 'Type' => 'int'),
            'parentid' => 'ParentCategoryID'
        );
        $Ex->exportTable('Category', "select f.*, title as Name2
         from :_forum f
         where 1 = 1 $ForumWhere", $Category_Map);

        $MinDiscussionID = false;
        $MinDiscussionWhere = false;
        if ($MinDate) {
            $MinDiscussionID = $Ex->getValue("
            select max(threadid)
            from :_thread
            where dateline < $MinDate
            ", false);

            $MinDiscussionID2 = $Ex->getValue("
            select min(threadid)
            from :_thread
            where dateline >= $MinDate
            ", false);

            // The two discussion IDs should be the same, but let's average them.
            $MinDiscussionID = floor(($MinDiscussionID + $MinDiscussionID2) / 2);

            $Ex->comment('Min topic id: ' . $MinDiscussionID);
        }

        // Discussions
        $Discussion_Map = array(
            'threadid' => 'DiscussionID',
            'forumid' => 'CategoryID',
            'postuserid' => 'InsertUserID',
            'postuserid2' => 'UpdateUserID',
            'title' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'Format' => 'Format',
            'views' => 'CountViews',
            'ipaddress' => 'InsertIPAddress'
        );

        if ($Ex->Destination == 'database') {
            // Remove the filter from the title so that this doesn't take too long.
            $Ex->HTMLDecoderDb('thread', 'title', 'threadid');
            unset($Discussion_Map['title']['Filter']);
        }

        if ($MinDiscussionID) {
            $MinDiscussionWhere = "and t.threadid > $MinDiscussionID";
        }

        $Ex->exportTable('Discussion', "select t.*,
            t.postuserid as postuserid2,
            p.ipaddress,
            p.pagetext as Body,
            'BBCode' as Format,
            replycount+1 as CountComments,
            convert(ABS(open-1),char(1)) as Closed,
            if(convert(sticky,char(1))>0,2,0) as Announce,
            FROM_UNIXTIME(t.dateline) as DateInserted,
            FROM_UNIXTIME(lastpost) as DateUpdated,
            FROM_UNIXTIME(lastpost) as DateLastComment
         from :_thread t
            left join :_deletionlog d on (d.type='thread' and d.primaryid=t.threadid)
            left join :_post p on p.postid = t.firstpostid
         where d.primaryid is null
            and t.visible = 1
            $MinDiscussionWhere
            $ForumWhere", $Discussion_Map);

        // Comments
        $Comment_Map = array(
            'postid' => 'CommentID',
            'threadid' => 'DiscussionID',
            'pagetext' => 'Body',
            'Format' => 'Format',
            'ipaddress' => 'InsertIPAddress'
        );

        if ($MinDiscussionID) {
            $MinDiscussionWhere = "and p.threadid > $MinDiscussionID";
        }

        $Ex->exportTable('Comment', "select p.*,
            'BBCode' as Format,
            p.userid as InsertUserID,
            p.userid as UpdateUserID,
         FROM_UNIXTIME(p.dateline) as DateInserted,
            FROM_UNIXTIME(p.dateline) as DateUpdated
         from :_post p
         inner join :_thread t
            on p.threadid = t.threadid
         left join :_deletionlog d
            on (d.type='post' and d.primaryid=p.postid)
         where p.postid <> t.firstpostid
            and d.primaryid is null
            and p.visible = 1
            $MinDiscussionWhere
            $ForumWhere", $Comment_Map);

        // UserDiscussion
        if ($MinDiscussionID) {
            $MinDiscussionWhere = "where st.threadid > $MinDiscussionID";
        }

        $Ex->exportTable('UserDiscussion', "select
            st.userid as UserID,
            st.threadid as DiscussionID,
            '1' as Bookmarked,
            FROM_UNIXTIME(tr.readtime) as DateLastViewed
         from :_subscribethread st
         left join :_threadread tr on tr.userid = st.userid and tr.threadid = st.threadid
         $MinDiscussionWhere");
        /*$Ex->exportTable('UserDiscussion', "select
             tr.userid as UserID,
             tr.threadid as DiscussionID,
             FROM_UNIXTIME(tr.readtime) as DateLastViewed,
             case when st.threadid is not null then 1 else 0 end as Bookmarked
           from :_threadread tr
           left join :_subscribethread st on tr.userid = st.userid and tr.threadid = st.threadid");*/

        // Activity (from visitor messages in vBulletin 3.8+)
        if ($Ex->exists('visitormessage')) {
            if ($MinDiscussionID) {
                $MinDiscussionWhere = "and dateline > $MinDiscussionID";
            }


            $Activity_Map = array(
                'postuserid' => 'RegardingUserID',
                'userid' => 'ActivityUserID',
                'pagetext' => 'Story',
                'NotifyUserID' => 'NotifyUserID',
                'Format' => 'Format'
            );
            $Ex->exportTable('Activity', "select *,
               '{RegardingUserID,you} &rarr; {ActivityUserID,you}' as HeadlineFormat,
               FROM_UNIXTIME(dateline) as DateInserted,
               FROM_UNIXTIME(dateline) as DateUpdated,
               INET_NTOA(ipaddress) as InsertIPAddress,
               postuserid as InsertUserID,
               -1 as NotifyUserID,
               'BBCode' as Format,
               'WallPost' as ActivityType
            from :_visitormessage
            where state='visible'
               $MinDiscussionWhere", $Activity_Map);
        }

        $this->_exportConversations($MinDate);

        $this->_exportPolls();

        // Media
        if ($Ex->exists('attachment')) {
            $this->exportMedia($MinDiscussionID);
        }

        // IP Ban list
        $IpBanlist = $this->param('ipbanlist');
        if ($IpBanlist) {

            $Ex->query("DROP TABLE IF EXISTS `z_ipbanlist` ");
            $Ex->query("CREATE TABLE `z_ipbanlist` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `ipaddress` varchar(50) DEFAULT NULL,
           PRIMARY KEY (`id`),
           UNIQUE KEY `ipaddress` (`ipaddress`)

         ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

            $Result = $Ex->query("select value from :_setting where varname = 'banip'");
            $Row = mysql_fetch_assoc($Result);

            if ($Row) {
                $InsertSql = 'INSERT IGNORE INTO `z_ipbanlist` (`ipaddress`) values ';
                $IpString = str_replace("\r", "", $Row['value']);
                $IPs = explode("\n", $IpString);
                foreach ($IPs as $IP) {
                    $IP = trim($IP);
                    if (empty($IP)) {
                        continue;
                    }
                    $InsertSql .= '(\'' . mysql_real_escape_string($IP) . '\'), ';
                }
                $InsertSql = substr($InsertSql, 0, -2);
                $Ex->query($InsertSql);

                $Ban_Map = array();
                $Ex->exportTable('Ban',
                    "select 'IPAddress' as BanType, ipaddress as BanValue, 'Imported ban' as Notes, NOW() as DateInserted
                  FROM `z_ipbanlist`",
                    $Ban_Map);

                $Ex->query('DROP table if exists `z_ipbanlist` ');

            }
        }


        // End
        $Ex->endExport();
    }

    protected function _exportConversations($MinDate) {
        $Ex = $this->Ex;

        if ($MinDate) {
            $MinID = $Ex->getValue("
            select max(pmtextid)
            from :_pmtext
            where dateline < $MinDate
            ", false);
        } else {
            $MinID = false;
        }
        $MinWhere = '';

        $Ex->query('drop table if exists z_pmto');
        $Ex->query('create table z_pmto (
        pmtextid int unsigned,
        userid int unsigned,
        primary key(pmtextid, userid)
      )');

        if ($MinID) {
            $MinWhere = "where pmtextid > $MinID";
        }

        $Ex->query("insert ignore z_pmto (
        pmtextid,
        userid
      )
      select
        pmtextid,
        userid
      from :_pm
      $MinWhere");

        $Ex->query("insert ignore z_pmto (
        pmtextid,
        userid
      )
      select
        pmtextid,
        fromuserid
      from :_pmtext
      $MinWhere;");

        $Ex->query("insert ignore z_pmto (
        pmtextid,
        userid
      )
      select
        pm.pmtextid,
        r.userid
      from :_pm pm
      join :_pmreceipt r
        on pm.pmid = r.pmid
      $MinWhere;");

        $Ex->query("insert ignore z_pmto (
        pmtextid,
        userid
      )
      select
        pm.pmtextid,
        r.touserid
      from :_pm pm
      join :_pmreceipt r
        on pm.pmid = r.pmid
      $MinWhere;");

        $Ex->query('drop table if exists z_pmto2;');
        $Ex->query('create table z_pmto2 (
        pmtextid int unsigned,
        userids varchar(250),
        primary key (pmtextid)
      );');

        $Ex->query('insert z_pmto2 (
        pmtextid,
        userids
      )
      select
        pmtextid,
        group_concat(userid order by userid)
      from z_pmto t
      group by t.pmtextid;');

        $Ex->query('drop table if exists z_pmtext;');
        $Ex->query('create table z_pmtext (
        pmtextid int unsigned,
        title varchar(250),
        title2 varchar(250),
        userids varchar(250),
        group_id int unsigned
      );');

        $Ex->query("insert z_pmtext (
        pmtextid,
        title,
        title2
      )
      select
        pmtextid,
        title,
        case when title like 'Re: %' then trim(substring(title, 4)) else title end as title2
      from :_pmtext pm
      $MinWhere;");
        $Ex->query('create index z_idx_pmtext on z_pmtext (pmtextid);');

        $Ex->query('update z_pmtext pm
      join z_pmto2 t
        on pm.pmtextid = t.pmtextid
      set pm.userids = t.userids;');

        // A conversation is a group of pmtexts with the same title and same users.

        $Ex->query('drop table if exists z_pmgroup;');
        $Ex->query('create table z_pmgroup (
        group_id int unsigned,
        title varchar(250),
        userids varchar(250)
      );');

        $Ex->query("insert z_pmgroup (
        group_id,
        title,
        userids
      )
      select
        min(pm.pmtextid),
        pm.title2,
        t2.userids
      from z_pmtext pm
      join z_pmto2 t2
        on pm.pmtextid = t2.pmtextid
      group by pm.title2, t2.userids;");

        $Ex->query('create index z_idx_pmgroup on z_pmgroup (title, userids);');
        $Ex->query('create index z_idx_pmgroup2 on z_pmgroup (group_id);');

        $Ex->query('update z_pmtext pm
      join z_pmgroup g
        on pm.title2 = g.title and pm.userids = g.userids
      set pm.group_id = g.group_id;');

        // Conversations.
        $Conversation_Map = array(
            'pmtextid' => 'ConversationID',
            'fromuserid' => 'InsertUserID',
            'title2' => array('Column' => 'Subject', 'Type' => 'varchar(250)')
        );
        $Ex->exportTable('Conversation',
            'select
         pm.*,
         g.title as title2,
         FROM_UNIXTIME(pm.dateline) as DateInserted
       from :_pmtext pm
       join z_pmgroup g
         on g.group_id = pm.pmtextid', $Conversation_Map);

        // Coversation Messages.
        $ConversationMessage_Map = array(
            'pmtextid' => 'MessageID',
            'group_id' => 'ConversationID',
            'message' => 'Body',
            'fromuserid' => 'InsertUserID'
        );
        $Ex->exportTable('ConversationMessage',
            "select
         pm.*,
         pm2.group_id,
         'BBCode' as Format,
         FROM_UNIXTIME(pm.dateline) as DateInserted
       from :_pmtext pm
       join z_pmtext pm2
         on pm.pmtextid = pm2.pmtextid", $ConversationMessage_Map);

        // User Conversation.
        $UserConversation_Map = array(
            'userid' => 'UserID',
            'group_id' => 'ConversationID'
        );
        $Ex->exportTable('UserConversation',
            "select
         g.group_id,
         t.userid
       from z_pmto t
       join z_pmgroup g
         on g.group_id = t.pmtextid;", $UserConversation_Map);

        $Ex->query('drop table if exists z_pmto');
        $Ex->query('drop table if exists z_pmto2;');
        $Ex->query('drop table if exists z_pmtext;');
        $Ex->query('drop table if exists z_pmgroup;');
    }

    /**
     * Converts database blobs into files.
     *
     * Creates /attachments and /customavatars folders in the same directory as the export file.
     *
     * @param bool $Attachments Whether to move attachments.
     * @param bool $CustomAvatars Whether to move avatars.
     */
    public function exportBlobs($Attachments = true, $CustomAvatars = true) {
        $Ex = $this->Ex;
        if ($Ex->exists('attachment', array('contenttypeid', 'contentid')) === true) {
            $Extension = ExportModel::fileExtension('a.filename');
        } else {
            $Extension = ExportModel::fileExtension('filename');
        }

        if ($Attachments) {
            $Identity = 'f.attachmentid';
            if ($Ex->exists('attachment', array('contenttypeid', 'contentid')) === true
                || $Ex->exists('attach') === true) {
                $Identity = 'f.filedataid';
            }

            $Sql = "select
               f.filedata,
               $Extension as extension,
               concat('attachments/', f.userid, '/', $Identity, '.', lower(extension)) as Path
               from ";

            // Table is dependent on vBulletin version (v4+ is filedata, v3 is attachment)
            if ($Ex->exists('attachment', array('contenttypeid', 'contentid')) === true) {
                $Sql .= ":_filedata f left join :_attachment a on a.filedataid = f.filedataid";
            } elseif ($Ex->exists('attach') === true) {
                $Sql .= ":_filedata f left join :_attach a on a.filedataid = f.filedataid";
            } else {
                $Sql .= ":_attachment f";
            }

            $Ex->exportBlobs($Sql, 'filedata', 'Path');
        }

        if ($CustomAvatars) {
            $Sql = "select
               a.filedata,
               case when a.userid is not null then concat('customavatars/', a.userid % 100,'/avatar_', a.userid, right(a.filename, instr(reverse(a.filename), '.')))
                  else null end as customphoto
            from :_customavatar a
            ";
            $Sql = str_replace('u.userid', 'a.userid', $Sql);
            $Ex->exportBlobs($Sql, 'filedata', 'customphoto', 80);
        }

        // Export the group icons no matter what.
        if ($Ex->exists('socialgroupicon', 'thumbnail_filedata') && ($Attachments || $CustomAvatars)) {
            $Sql = "
            select
               i.filedata,
               concat('vb/groupicons/', i.groupid, '.', i.extension) as path
            from :_socialgroupicon i";
            $Ex->exportBlobs($Sql, 'filedata', 'path');
        }
    }

    /**
     * Export the attachments as Media.
     *
     * In vBulletin 4.x, the filedata table was introduced.
     */
    public function exportMedia($MinDiscussionID = false) {
        $Ex = $this->Ex;
        $instance = $this;

        if ($MinDiscussionID) {
            $DiscussionWhere = "and t.threadid > $MinDiscussionID";
        } else {
            $DiscussionWhere = '';
        }
        $Media_Map = array(
            'attachmentid' => 'MediaID',
            'filename' => 'Name',
            'filesize' => 'Size',
            'userid' => 'InsertUserID',
            'extension' => array('Column' => 'Type', 'Filter' => array($this, 'BuildMimeType')),
            'filehash' => array('Column' => 'Path', 'Filter' => array($this, 'BuildMediaPath')),
            'filethumb' => array(
                'Column' => 'ThumbPath',
                'Filter' => function($Value, $Field, $Row) use ($instance) {
                    $filteredData = $this->filterThumbnailData($Value, $Field, $Row);

                    if ($filteredData) {
                        return $instance->buildMediaPath($Value, $Field, $Row);
                    } else {
                        return null;
                    }
                }
            ),
            'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'filterThumbnailData')),
            'height' => array('Column' => 'ImageHeight', 'Filter' => array($this, 'buildMediaDimension')),
            'width' => array('Column' => 'ImageWidth', 'Filter' => array($this, 'buildMediaDimension')),
        );

        // Add hash fields if they exist (from 2.x)
        $AttachColumns = array('hash', 'filehash');
        $Missing = $Ex->exists('attachment', $AttachColumns);
        $AttachColumnsString = '';
        foreach ($AttachColumns as $ColumnName) {
            if (in_array($ColumnName, $Missing)) {
                $AttachColumnsString .= ", null as $ColumnName";
            } else {
                $AttachColumnsString .= ", a.$ColumnName";
            }
        }
        // Do the export
        if ($Ex->exists('attachment', array('contenttypeid', 'contentid')) === true) {
            // Exporting 4.x with 'filedata' table.
            // Build an index to join on.
            $Result = $Ex->query('show index from :_thread where Key_name = "ix_thread_firstpostid"');
            if (!$Result) {
                $Ex->query('create index ix_thread_firstpostid on :_thread (firstpostid)');
            }
            $MediaSql = "
                select
                    case
                        when t.threadid is not null then 'discussion'
                        when ct.class = 'Post' then 'comment'
                        when ct.class = 'Thread' then 'discussion'
                        else ct.class
                    end as ForeignTable,
                    case
                        when t.threadid is not null then t.threadid
                        else a.contentid
                    end as ForeignID,
                    FROM_UNIXTIME(a.dateline) as DateInserted,
                    a.*,
                    f.extension,
                    f.filesize/*,*/
                    $AttachColumnsString,
                    f.width,
                    f.height,
                    'mock_value' as filethumb,
                    128 as thumb_width
                from :_attachment a
                    join :_contenttype ct on a.contenttypeid = ct.contenttypeid
                    join :_filedata f on f.filedataid = a.filedataid
                    left join :_thread t on t.firstpostid = a.contentid and a.contenttypeid = 1
                where a.contentid > 0
                    $DiscussionWhere
            ";
            $Ex->exportTable('Media', $MediaSql, $Media_Map);

        } else {
            // Exporting 3.x without 'filedata' table.
            // Do NOT grab every field to avoid 'filedata' blob in 3.x.
            // Left join 'attachment' because we can't left join 'thread' on firstpostid (not an index).
            // Lie about the height & width to spoof FileUpload serving generic thumbnail if they aren't set.
            $Extension = ExportModel::FileExtension('a.filename');
            $MediaSql = "
                select
                    a.attachmentid,
                    a.filename,
                    $Extension as extension/*,*/
                    $AttachColumnsString,
                    a.userid,
                    'discussion' as ForeignTable,
                    t.threadid as ForeignID,
                    FROM_UNIXTIME(a.dateline) as DateInserted,
                    '1' as height,
                    '1' as width,
                    'mock_value' as filethumb,
                    128 as thumb_width
                from :_thread t
                    left join :_attachment a ON a.postid = t.firstpostid
                where a.attachmentid > 0

                union all

                select
                    a.attachmentid,
                    a.filename,
                    $Extension as extension/*,*/
                    $AttachColumnsString,
                    a.userid,
                    'comment' as ForeignTable,
                    a.postid as ForeignID,
                    FROM_UNIXTIME(a.dateline) as DateInserted,
                    '1' as height,
                    '1' as width,
                    'mock_value' as filethumb,
                    128 as thumb_width
                from :_post p
                    inner join :_thread t ON p.threadid = t.threadid
                    left join :_attachment a ON a.postid = p.postid
                where p.postid <> t.firstpostid and a.attachmentid > 0
            ";
            $Ex->exportTable('Media', $MediaSql, $Media_Map);
        }

        // files named .attach need to be named properly.
        // file needs to be renamed and db updated.
        // if its an images; we need to include .thumb
        $attachmentPath = $this->param('filepath');
        if ($attachmentPath) {
            $missingFiles = array();
            if (is_dir($attachmentPath)) {
                $Ex->comment("Checking files");
                $Result = $Ex->query($MediaSql);
                while ($row = mysql_fetch_assoc($Result)) {
                    $filePath = $this->buildMediaPath('', '', $row);
                    $cdn = $this->param('cdn', '');

                    if (!empty($cdn)) {
                        $filePath = str_replace($cdn, '', $filePath);
                    }
                    $fullPath = $attachmentPath . $filePath;
                    if (file_exists($fullPath)) {
                        continue;
                    }

                    //check if named .attach
                    $p = explode('.', $fullPath);
                    $attachFilename = str_replace(end($p), 'attach', $fullPath);
                    if (file_exists($attachFilename)) {
                        // rename file
                        rename($attachFilename, $fullPath);
                        continue;
                    }

                    //check if md5 hash in root
                    if (getValue('hash', $row)) {
                        $md5Filename = $attachmentPath . $row['hash'] . '.' . $row['extension'];
                        if (file_exists($md5Filename)) {
                            // rename file
                            rename($md5Filename, $fullPath);
                            continue;
                        }
                    }

                    $missingFiles[] = $filePath;

                }
            } else {
                $Ex->comment('Attachment Path not found');
            }
            $totalMissingFiles = count($missingFiles);
            if ($totalMissingFiles > 0) {
                $Ex->comment('Missing files detected.  See ./missing_files.txt for full list.');
                $Ex->comment(sprintf('Total missing files %d', $totalMissingFiles));
                file_put_contents('missing-files.txt', implode("\n", $missingFiles));
            }

        }
    }

    protected function _exportPolls() {
        $Ex = $this->Ex;
        $fp = $Ex->File;
//      $fp = fopen('php://output', 'ab');

        $Poll_Map = array(
            'pollid' => 'PollID',
            'question' => 'Name',
            'threadid' => 'DiscussionID',
            'anonymous' => 'Anonymous',
            'dateline' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'postuserid' => 'InsertUserID'
        );
        $Ex->exportTable('Poll',
            "select
            p.*,
            t.threadid,
            t.postuserid,
            !p.public as anonymous
         from :_poll p
         join :_thread t
            on p.pollid = t.pollid", $Poll_Map);

        $PollOption_Map = array(
            'optionid' => 'PollOptionID', // calc
            'pollid' => 'PollID',
            'body' => 'Body', // calc
            'sort' => 'Sort', // calc
            'dateline' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'postuserid' => 'InsertUserID'
        );
        $Sql = "select
         p.*,
         'BBCode' as Format,
         t.postuserid
      from :_poll p
      join :_thread t
         on p.pollid = t.pollid";

        // Some custom programming needs to be done here so let's do that.
        $ExportStructure = $Ex->getExportStructure($PollOption_Map, 'PollOption', $PollOption_Map);
        $RevMappings = $Ex->flipMappings($PollOption_Map);

        $Ex->writeBeginTable($fp, 'PollOption', $ExportStructure);

        $r = $Ex->query($Sql);
        $RowCount = 0;
        while ($Row = mysql_fetch_assoc($r)) {
            $Options = explode('|||', $Row['options']);

            foreach ($Options as $i => $Option) {
                $Row['optionid'] = $Row['pollid'] * 1000 + $i + 1;
                $Row['body'] = $Option;
                $Row['sort'] = $i;

                $Ex->writeRow($fp, $Row, $ExportStructure, $RevMappings);

                $RowCount++;
            }
        }
        mysql_free_result($r);
        $Ex->writeEndTable($fp);
        $Ex->comment("Exported Table: PollOption ($RowCount rows)");

        $PollVote_Map = array(
            'userid' => 'UserID',
            'optionid' => 'PollOptionID',
            'votedate' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate')
        );
        $Ex->exportTable('PollVote',
            "select pv.*, pollid * 1000 + voteoption as optionid
         from :_pollvote pv", $PollVote_Map);
    }

    /**
     * Filter used by $Media_Map to build attachment path.
     *
     * vBulletin 3.0+ organizes its attachments by descending 1 level per digit
     * of the userid, named as the attachmentid with a '.attach' extension.
     * Example: User #312's attachments would be in the directory /3/1/2.
     *
     * In vBulletin 2.x, files were stored as an md5 hash in the root
     * attachment directory with a '.file' extension. Existing files were not
     * moved when upgrading to 3.x so older forums will need those too.
     *
     * @access public
     * @see ExportModel::_exportTable
     *
     * @param string $Value Ignored.
     * @param string $Field Ignored.
     * @param array $Row Contents of the current attachment record.
     * @return string Future path to file.
     */
    public function buildMediaPath($Value, $Field, $Row) {
        if (isset($Row['hash']) && $Row['hash'] != '') {
            // Old school! (2.x)
            $FilePath = $Row['hash'] . '.' . $Row['extension'];
        } else { // Newer than 3.0
            // Build user directory path
            $n = strlen($Row['userid']);
            $DirParts = array();
            for ($i = 0; $i < $n; $i++) {
                $DirParts[] = $Row['userid']{$i};
            }

            // 3.x uses attachmentid, 4.x uses filedataid
            $Identity = (isset($Row['filedataid'])) ? $Row['filedataid'] : $Row['attachmentid'];

            // If we're exporting blobs, simplify the folder structure.
            // Otherwise, we need to preserve vBulletin's eleventy subfolders.
            $Separator = ($this->param('files', false)) ? '' : '/';
            $FilePath = implode($Separator, $DirParts) . '/' . $Identity . '.' . $Row['extension'];
        }

        // Use 'cdn' parameter to define path prefix, ex: ?cdn=~cf/
        $Cdn = $this->param('cdn', '');

        return $Cdn . 'attachments/' . $FilePath;
    }

    /**
     * Don't allow image dimensions to creep in for non-images.
     *
     * @param $Value
     * @param $Field
     * @param $Row
     */
    public function buildMediaDimension($Value, $Field, $Row) {
        // Non-images get no height/width
        $Ex = $this->Ex;
        if ($Ex->exists('attachment', array('extension'))) {
            $extension = $Row['extension'];
        } else {
            $extension = end(explode('.', $Row['filename']));
        }
        if (in_array(strtolower($extension), array('jpg', 'gif', 'png', 'jpeg'))) {
            return null;
        }

        return $Value;
    }

    /**
     * Set valid MIME type for images.
     *
     * @access public
     * @see ExportModel::_exportTable
     *
     * @param string $Value Extension from vBulletin.
     * @param string $Field Ignored.
     * @param array $Row Ignored.
     * @return string Extension or accurate MIME type.
     */
    public function buildMimeType($Value, $Field, $Row) {
        switch (strtolower($Value)) {
            case 'jpg':
            case 'gif':
            case 'png':
                $Value = 'image/' . $Value;
                break;
            case 'pdf':
            case 'zip':
                $Value = 'application/' . $Value;
                break;
            case 'doc':
                $Value = 'application/msword';
                break;
            case 'xls':
                $Value = 'application/vnd.ms-excel';
                break;
            case 'txt':
                $Value = 'text/plain';
                break;
        }

        return $Value;
    }

    /**
     * Filter used by $Media_Map to replace value for ThumbPath and ThumbWidth when the file is not an image.
     *
     * @access public
     * @see ExportModel::_exportTable
     *
     * @param string $value Current value
     * @param string $field Current field
     * @param array $row Contents of the current record.
     * @return string|null Return the supplied value if the record's file is an image. Return null otherwise
     */
    public function filterThumbnailData($value, $field, $row) {
        if (strpos(MimeTypeFromExtension(strtolower($row['extension'])), 'image/') === 0) {
            return $value;
        } else {
            return null;
        }
    }

    /**
     * Determine if this usergroup could likely sign in to forum based on its name.
     *
     * @param $Value
     * @param $Field
     * @param $Row
     * @return bool
     */
    public function signInPermission($Value, $Field, $Row) {
        $Result = true;
        if (stripos($Row['title'], 'unregistered') !== false) {
            $Result = false;
        } elseif (stripos($Row['title'], 'banned') !== false) {
            $Result = false;
        }

        return $Result;
    }

    /**
     * Retrieve a value from the vBulletin setting table.
     *
     * @param string $Name Variable for which we want the value.
     * @return mixed Value or FALSE if not found.
     */
    public function getConfig($Name) {
        $Sql = "select * from :_setting where varname = '$Name'";
        $Result = $this->Ex->query($Sql, true);
        if ($Row = mysql_fetch_assoc($Result)) {
            return $Row['value'];
        }

        return false;
    }

    /**
     * @param $Value
     * @param $Field
     * @param $Row
     * @return bool
     */
    public function filterPermissions($Value, $Field, $Row) {
        if (!isset(self::$Permissions2[$Field])) {
            return 0;
        }

        $Column = self::$Permissions2[$Field][0];
        $Mask = self::$Permissions2[$Field][1];

        $Value = ($Row[$Column] & $Mask) == $Mask;

        return $Value;
    }

    /**
     * @param $ColumnGroups
     * @param $Map
     */
    public function addPermissionColumns($ColumnGroups, &$Map) {
        $Permissions2 = array();

        foreach ($ColumnGroups as $ColumnGroup => $Columns) {
            foreach ($Columns as $Mask => $ColumnArray) {
                $ColumnArray = (array)$ColumnArray;
                foreach ($ColumnArray as $Column) {
                    $Map[$Column] = array(
                        'Column' => $Column,
                        'Type' => 'tinyint(1)',
                        'Filter' => array($this, 'filterPermissions')
                    );

                    $Permissions2[$Column] = array($ColumnGroup, $Mask);
                }
            }
        }
        self::$Permissions2 = $Permissions2;
    }
}
