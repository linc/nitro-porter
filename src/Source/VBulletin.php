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
 * To extract avatars stored in database, add 'db-avatars=1' parameter to the URL.
 * To extract attachments stored in db, add 'db-files=1' parameter to the URL.
 * To extract all usermeta data (title, skype, custom profile fields, etc),
 *    add 'usermeta=1' parameter to the URL.
 * To stop the export after only extracting files, add 'files-only=1' param to the URL.
 *
 * TO MIGRATE FILES, BEFORE IMPORTING YOU MUST:
 * 1) Copy entire 'customavatars' folder into Vanilla's /upload folder.
 * 2) Copy entire 'attachments' folder into Vanilla's / upload folder.
 * 3) Make BOTH folders writable by the server.
 * 4) Enable the FileUpload plugin. (Media table must be present.)
 *
 * files-source - Command line option to fix / check files are on disk.  Files named .attach are renamed
 * to the proper name and missing files are reported in missing-files.txt.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class VBulletin extends Source
{
    public const SUPPORTED = [
        'name' => 'vBulletin 3 & 4',
        'prefix' => 'vb_',
        'charset_table' => 'post',
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
            'files-only' => [
                'Skip all exports except files stored in database.',
                'Sx' => '::'
            ],
            'mindate' => [
                'A date to import from. Like selective amnesia.'
            ],
            'forumid' => [
                'Only export 1 forum (category) with given ID.'
            ],
            //'ipbanlist' => [
            //    'Export IP ban list, which is a terrible idea.'
            //],
            'files-source' => [
                'Full path of file attachments to be renamed.',
                'Sx' => '::'
            ],
            'separator' => [
                'Character used to split the hash of attachments. ("" or "/")',
                'Sx' => '::'
            ],
        ],
        'features' => [
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
            'Tags' => 1,
            'Reactions' => 1,
        ]
    ];

    /* @var string SQL fragment to build new path to attachments. */
    public $attachSelect = "concat('/vbulletin/', left(f.filehash, 2), '/',
        f.filehash, '_', a.attachmentid,'.', f.extension) as Path";

    /* @var string SQL fragment to build new path to user photo. */
    public $avatarSelect = "
        case
            when a.userid is not null then concat('customavatars/',
                a.userid % 100,'/avatar_', a.userid, right(a.filename, instr(reverse(a.filename), '.')))
            when av.avatarpath is not null then av.avatarpath
            else null
        end as customphoto
    ";
    /* @var array Default permissions to map. */
    public static $permissions = array(

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

    public static $permissions2 = array();

    /**
     * @var array Required tables => columns. Commented values are optional.
     */
    public $sourceTables = array(
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
     * @param ExportModel $ex
     */
    public function run($ex)
    {
        $this->exportModel = $ex; // @todo

        // Allow limited export of 1 category via ?forumid=ID
        $forumID = $this->param('forumid');
        if ($forumID) {
            $forumWhere = ' and t.forumid ' . (strpos($forumID, ', ') === false ? "= $forumID" : "in ($forumID)");
        } else {
            $forumWhere = '';
        }

        $this->exportBlobs(
            $ex,
            $this->param('db-files'),
            $this->param('db-avatars')
        );

        if ($this->param('files-only')) {
            $ex->comment('Skipping the export.');
            return;
        }

        $minDiscussionID = false;
        $minDiscussionWhere = false;

        // Check to see if there is a max date.
        $minDate = $this->param('mindate');
        if ($minDate) {
            $minDate = strtotime($minDate);
            $ex->comment("Min topic date ($minDate): " . date('c', $minDate));
        }

        $cdn = $this->param('cdn', '');

        // Ranks
        $ranks = $this->ranks($ex);

        $this->users($ex, $ranks, $cdn);

        $this->roles($ex);
        $this->permissions($ex);
        $this->userMeta($ex, $forumWhere, $minDate);

        $this->discussions($ex, $minDiscussionID, $forumWhere);
        $this->comments($ex, $minDiscussionID, $forumWhere);

        // UserDiscussion
        if ($minDiscussionID) {
            $minDiscussionWhere = "where st.threadid > $minDiscussionID";
        }

        if ($ex->exists('threadread', array('readtime')) === true) {
            $threadReadTime = 'from_unixtime(tr.readtime)';
            $threadReadJoin = 'left join :_threadread as tr on tr.userid = st.userid and tr.threadid = st.threadid';
        } else {
            $threadReadTime = 'now()';
            $threadReadJoin = null;
        }

        $ex->exportTable(
            'UserDiscussion',
            "
            select
                st.userid as UserID,
                st.threadid as DiscussionID,
                $threadReadTime as DateLastViewed,
                '1' as Bookmarked
            from :_subscribethread as st
                $threadReadJoin
                $minDiscussionWhere"
        );

        $this->wallPosts($ex, $minDiscussionID);

        $this->conversations($ex);
        $this->polls($ex);

        // Media
        if ($ex->exists('attachment') === true) {
            $this->attachments($ex, $minDiscussionID);
        }

        $this->ipbans($ex);
        $this->tags($ex);

        // Reactions
        if ($ex->exists('post_thanks') === true) {
            $this->reactions($ex);
        }
    }

    /**
     * Converts database blobs into files.
     *
     * Creates /attachments and /customavatars folders in the same directory as the export file.
     *
     * @param bool $attachments   Whether to move attachments.
     * @param bool $customAvatars Whether to move avatars.
     */
    public function exportBlobs($ex, $attachments = true, $customAvatars = true)
    {
        if ($attachments) {
            $identity = 'f.attachmentid';

            if ($ex->exists('attachment', array('contenttypeid', 'contentid')) === true) {
                $extension = ExportModel::fileExtension('a.filename');
                $identity = 'f.filedataid';
            } elseif ($ex->exists('attach') === true) {
                $identity = 'f.filedataid';
            } else {
                $extension = ExportModel::fileExtension('filename');
            }

            $sql = "select
                   f.filedata,
                   $extension as extension,
                   concat('attachments/', f.userid, '/', $identity, '.', lower($extension)) as Path
               from ";

            // Table is dependent on vBulletin version (v4+ is filedata, v3 is attachment)
            if ($ex->exists('attachment', array('contenttypeid', 'contentid')) === true) {
                $sql .= ":_filedata f left join :_attachment a on a.filedataid = f.filedataid";
            } elseif ($ex->exists('attach') === true) {
                $sql .= ":_filedata f left join :_attach a on a.filedataid = f.filedataid";
            } else {
                $sql .= ":_attachment f";
            }

            $ex->exportBlobs($sql, 'filedata', 'Path');
        }

        if ($customAvatars) {
            if ($ex->exists('customavatar', array('avatardata')) === true) {
                $avatarDataColumn = 'avatardata';
            } else {
                $avatarDataColumn = 'filedata';
            }

            $sql = "select
                   a.$avatarDataColumn,
                   if (a.userid is not null,
                       concat('customavatars/', a.userid % 100,'/avatar_', a.userid,
                        right(a.filename, instr(reverse(a.filename), '.'))),
                       null
                   ) as customphoto
                from :_customavatar a";
            $sql = str_replace('u.userid', 'a.userid', $sql);
            $ex->exportBlobs($sql, $avatarDataColumn, 'customphoto', 80);
        }

        // Export the group icons no matter what.
        if ($ex->exists('socialgroupicon', 'thumbnail_filedata') === true && ($attachments || $customAvatars)) {
            $ex->exportBlobs(
                "select
                   i.filedata,
                   concat('vb/groupicons/', i.groupid, '.', i.extension) as path
                from :_socialgroupicon i",
                'filedata',
                'path'
            );
        }
    }

    /**
     * Export the attachments as Media.
     *
     * In vBulletin 4.x, the filedata table was introduced.
     */
    public function attachments($ex, $minDiscussionID = false)
    {
        $instance = $this;

        if ($minDiscussionID) {
            $discussionWhere = "and t.threadid > $minDiscussionID";
        } else {
            $discussionWhere = '';
        }
        $media_Map = array(
            'attachmentid' => 'MediaID',
            'filename' => 'Name',
            'filesize' => 'Size',
            'userid' => 'InsertUserID',
            'extension' => array('Column' => 'Type', 'Filter' => array($this, 'buildMimeType')),
            'filehash' => array('Column' => 'Path', 'Filter' => array($this, 'buildMediaPath')),
            'filethumb' => array(
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
            'height' => array('Column' => 'ImageHeight', 'Filter' => array($this, 'buildMediaDimension')),
            'width' => array('Column' => 'ImageWidth', 'Filter' => array($this, 'buildMediaDimension')),
        );

        // Add hash fields if they exist (from 2.x)
        $attachColumns = array('hash', 'filehash');
        $missing = $ex->exists('attachment', $attachColumns);
        $attachColumnsString = '';
        foreach ($attachColumns as $columnName) {
            if (in_array($columnName, $missing)) {
                $attachColumnsString .= ", null as $columnName";
            } else {
                $attachColumnsString .= ", a.$columnName";
            }
        }
        // Do the export
        if ($ex->exists('attachment', array('contenttypeid', 'contentid')) === true) {
            // Exporting 4.x with 'filedata' table.
            // Build an index to join on.
            if (!$ex->indexExists('ix_thread_firstpostid', ':_thread')) {
                $ex->query('create index ix_thread_firstpostid on :_thread (firstpostid)');
            }
            $mediaSql = "select
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
                    from_unixtime(a.dateline) as DateInserted,
                    a.*,
                    f.extension,
                    f.filesize/*,*/
                    $attachColumnsString,
                    f.width,
                    f.height,
                    'mock_value' as filethumb,
                    128 as thumb_width
                from :_attachment a
                    join :_contenttype ct on a.contenttypeid = ct.contenttypeid
                    join :_filedata f on f.filedataid = a.filedataid
                    left join :_thread t on t.firstpostid = a.contentid and a.contenttypeid = 1
                where a.contentid > 0
                    $discussionWhere";
            $ex->exportTable('Media', $mediaSql, $media_Map);
        } else {
            // Exporting 3.x without 'filedata' table.
            // Do NOT grab every field to avoid 'filedata' blob in 3.x.
            // Left join 'attachment' because we can't left join 'thread' on firstpostid (not an index).
            // Lie about the height & width to spoof FileUpload serving generic thumbnail if they aren't set.
            $extension = ExportModel::fileExtension('a.filename');
            $mediaSql = "select
                    a.attachmentid,
                    a.filename,
                    $extension as extension/*,*/
                    $attachColumnsString,
                    a.userid,
                    'discussion' as ForeignTable,
                    t.threadid as ForeignID,
                    from_unixtime(a.dateline) as DateInserted,
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
                    $extension as extension/*,*/
                    $attachColumnsString,
                    a.userid,
                    'comment' as ForeignTable,
                    a.postid as ForeignID,
                    from_unixtime(a.dateline) as DateInserted,
                    '1' as height,
                    '1' as width,
                    'mock_value' as filethumb,
                    128 as thumb_width
                from :_post p
                    inner join :_thread t ON p.threadid = t.threadid
                    left join :_attachment a ON a.postid = p.postid
                where p.postid <> t.firstpostid and a.attachmentid > 0";
            $ex->exportTable('Media', $mediaSql, $media_Map);
        }

        // files named .attach need to be named properly.
        // file needs to be renamed and db updated.
        // if its an images; we need to include .thumb
        $attachmentPath = $this->param('files-source');
        if ($attachmentPath) {
            $missingFiles = array();
            if (is_dir($attachmentPath)) {
                $ex->comment("Checking files");
                $result = $ex->query($mediaSql);
                while ($row = $result->nextResultRow()) {
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
                $ex->comment('Attachment Path not found');
            }
            $totalMissingFiles = count($missingFiles);
            if ($totalMissingFiles > 0) {
                $ex->comment('Missing files detected.  See ./missing_files.txt for full list.');
                $ex->comment(sprintf('Total missing files %d', $totalMissingFiles));
                file_put_contents('missing-files.txt', implode("\n", $missingFiles));
            }
        }
    }

    protected function polls($ex)
    {
        $poll_Map = array(
            'pollid' => 'PollID',
            'question' => 'Name',
            'threadid' => 'DiscussionID',
            'anonymous' => 'Anonymous',
            'dateline' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'postuserid' => 'InsertUserID'
        );
        $ex->exportTable(
            'Poll',
            "select
                    p.*,
                    t.threadid,
                    t.postuserid,
                    !p.public as anonymous
                from :_poll p
                    join :_thread t on p.pollid = t.pollid",
            $poll_Map
        );


        // Poll options
        $ex->query("drop table if exists zPollOptions;");
        $ex->query(
            "create table zPollOptions (
                PollOptionID int(11) NOT NULL AUTO_INCREMENT,
                PollID int(11),
                Body varchar(250),
                Sort int(11),
                DateInserted int(11),
                InsertUserID int(11),
                PRIMARY KEY (`PollOptionID`)
            );"
        );

        $sql = "select p.*, t.postuserid
            from :_poll p
            join :_thread t on p.pollid = t.pollid";

        $r = $ex->query($sql);
        $rowCount = 0;
        $sql  = "replace into zPollOptions (
                    PollOptionID,
                    PollID,
                    Body,
                    Sort,
                    DateInserted,
                    InsertUserID
                ) values ";
        while ($row = $r->nextResultRow()) {
            $options = explode('|||', $row['options']);

            foreach ($options as $i => $option) {
                $rowCount++;
                $option = addslashes($option);

                $sql .= "(
                        {$rowCount},
                        {$row['pollid']},
                        '{$option}',
                        {$i},
                        {$row['dateline']},
                        {$row['postuserid']}
                    ),";
            }
        }

        if ($rowCount > 0) {
            $ex->query(substr($sql, 0, -1));
        }

        $ex->exportTable(
            'PollOption',
            "select
                PollOptionID,
                PollID,
                Body,
                'BBCdode' as Format,
                Sort,
                FROM_UNIXTIME(DateInserted),
                InsertUserID
            from zPollOptions"
        );

        $ex->exportTable(
            'PollVote',
            "select
                pv.userid as UserID,
                zp.PollOptionID,
                pv.pollid
            from :_pollvote pv
            join zPollOptions zp on pv.pollid = zp.PollID and pv.voteoption = zp.sort"
        );
    }

    public function ranks($ex): array
    {
        $rank = $ex->query("select count(*) from :_ranks");

        if ($rank->nextResultRow() > 0) {
            $ranks = $ex->get(
                "select rankid as RankID, minposts
                   from :_ranks
                   where minposts > 0
                   order by minposts desc"
            );

            $ex->exportTable(
                'Rank',
                "select
                    rankid as RankID,
                    rankimg as Name,
                    rankimg as Label,
                    NULL as Body,
                    concat('{\"Criteria\":{\"CountPosts\":\"', minposts, '\"}}') as Attributes
                    from :_ranks
                    where minposts > 0"
            );
        } else {
            $ranks = $ex->get("select * from :_usertitle order by minposts desc", 'usertitleid');

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

            $ex->exportTable(
                'Rank',
                "select ut.*,
                        ut.title as title2,
                        0 as level
                    from :_usertitle as ut
                    order by ut.minposts",
                $rank_Map
            );
        }

        return $ranks;
    }

    /**
     * Filter used by $media_Map to build attachment path.
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
     * @param  string $value Ignored.
     * @param  string $field Ignored.
     * @param  array  $row   Contents of the current attachment record.
     * @return string Future path to file.
     *@see    ExportModel::exportTableWrite
     *
     */
    public function buildMediaPath($value, $field, $row)
    {
        if (isset($row['hash']) && $row['hash'] != '') {
            // Old school! (2.x)
            $filePath = $row['hash'] . '.' . $row['extension'];
        } else { // Newer than 3.0
            // Build user directory path
            $chars = str_split($row['userid']);
            $dirParts = array();
            foreach ($chars as $char) {
                $dirParts[] = $char;
            }

            // 3.x uses attachmentid, 4.x uses filedataid
            $identity = (isset($row['filedataid'])) ? $row['filedataid'] : $row['attachmentid'];

            // If we're exporting blobs, simplify the folder structure.
            // Otherwise, we need to preserve vBulletin's eleventy subfolders.
            $separator = $this->param('separator', '');
            $filePath = implode($separator, $dirParts) . '/' . $identity . '.' . $row['extension'];
        }

        // Use 'cdn' parameter to define path prefix, ex: ?cdn=~cf/
        $cdn = $this->param('cdn', '');

        return $cdn . 'attachments/' . $filePath;
    }

    /**
     * Don't allow image dimensions to creep in for non-images.
     *
     * @param $value
     * @param $field
     * @param $row
     */
    public function buildMediaDimension($value, $field, $row)
    {
        // Non-images get no height/width
        if ($this->exportModel->exists('attachment', array('extension')) === true) {
            $extension = $row['extension'];
        } else {
            $extension = pathinfo($row['filename'], PATHINFO_EXTENSION);
        }
        if (in_array(strtolower($extension), array('jpg', 'gif', 'png', 'jpeg'))) {
            return null;
        }

        return $value;
    }

    /**
     * Set valid MIME type for images.
     *
     * @access public
     * @param  string $value Extension from vBulletin.
     * @param  string $field Ignored.
     * @param  array  $row   Ignored.
     * @return string Extension or accurate MIME type.
     *@see    ExportModel::exportTableWrite
     *
     */
    public function buildMimeType($value, $field, $row)
    {
        switch (strtolower($value)) {
            case 'jpg':
            case 'gif':
            case 'png':
                $value = 'image/' . $value;
                break;
            case 'pdf':
            case 'zip':
                $value = 'application/' . $value;
                break;
            case 'doc':
                $value = 'application/msword';
                break;
            case 'xls':
                $value = 'application/vnd.ms-excel';
                break;
            case 'txt':
                $value = 'text/plain';
                break;
        }

        return $value;
    }

    /**
     * Filter used by $Media_Map to replace value for ThumbPath and ThumbWidth when the file is not an image.
     *
     * @access public
     * @param  string $value Current value
     * @param  string $field Current field
     * @param  array  $row   Contents of the current record.
     * @return string|null Return the supplied value if the record's file is an image. Return null otherwise
     *@see    ExportModel::exportTableWrite
     *
     */
    public function filterThumbnailData($value, $field, $row)
    {
        if (strpos(mimeTypeFromExtension(strtolower($row['extension'])), 'image/') === 0) {
            return $value;
        } else {
            return null;
        }
    }

    /**
     * Determine if this usergroup could likely sign in to forum based on its name.
     *
     * @param  $value
     * @param  $field
     * @param  $row
     * @return bool
     */
    public function signInPermission($value, $field, $row)
    {
        $result = true;
        if (stripos($row['title'], 'unregistered') !== false) {
            $result = false;
        } elseif (stripos($row['title'], 'banned') !== false) {
            $result = false;
        }

        return $result;
    }

    /**
     * Retrieve a value from the vBulletin setting table.
     *
     * @param $ex
     * @param  string $name Variable for which we want the value.
     * @return mixed Value or FALSE if not found.
     */
    public function getConfig($ex, $name)
    {
        $sql = "select * from :_setting where varname = '$name'";
        $result = $ex->query($sql, true);
        if ($row = $result->nextResultRow()) {
            return $row['value'];
        }

        return false;
    }

    /**
     * @param  $value
     * @param  $field
     * @param  $row
     * @return bool
     */
    public function filterPermissions($value, $field, $row)
    {
        if (!isset(self::$permissions2[$field])) {
            return 0;
        }

        $column = self::$permissions2[$field][0];
        $mask = self::$permissions2[$field][1];

        $value = ($row[$column] & $mask) == $mask;

        return $value;
    }

    /**
     * @param $columnGroups
     * @param $map
     */
    public function addPermissionColumns($columnGroups, &$map)
    {
        $permissions2 = array();

        foreach ($columnGroups as $columnGroup => $columns) {
            foreach ($columns as $mask => $columnArray) {
                $columnArray = (array)$columnArray;
                foreach ($columnArray as $column) {
                    $map[$column] = array(
                        'Column' => $column,
                        'Type' => 'tinyint(1)',
                        'Filter' => array($this, 'filterPermissions')
                    );

                    $permissions2[$column] = array($columnGroup, $mask);
                }
            }
        }
        self::$permissions2 = $permissions2;
    }

    /**
     * @param $value
     * @return mixed
     */
    public function htmlDecode($value)
    {
        return ($value);
    }

    /**
     * @param ExportModel $ex
     * @return void
     */
    protected function tags(ExportModel $ex): void
    {
        $ex->exportTable(
            'Tag',
            "
            select
                tagid as TagID,
                replace(lower(tagtext), ' ', '-') as Name,
                tagtext as FullName ,
                from_unixtime(dateline) as DateInserted
            from :_tag
        "
        );

        $ex->exportTable(
            'TagDiscussion',
            "select
                    tagid as TagID,
                    threadid as DiscussionID,
                    -1 as CategoryID,
                    from_unixtime(dateline) as DateInserted
                from :_tagthread"
        );
    }

    /**
     * @param ExportModel $ex
     * @return void
     */
    protected function conversations(ExportModel $ex): void
    {
        $ex->exportTable(
            'Conversation',
            "select
                p.parentpmid as ConversationID,
                 replace(t.title, 'Re: ', '') as Subject,
                t.fromuserid as InsertUserID,
                from_unixtime(t.dateline),
                t.pmtextid as FirstMessageID
            from (select
                parentpmid,
                min(p.pmtextid) as pmtextid
            from (
                select pmtextid, parentpmid from :_pm where parentpmid <> 0 group by pmtextid having count(pmtextid) > 1
            ) p
            group by parentpmid)	p
            join :_pmtext t on t.pmtextid = p.pmtextid"
        );

        $ex->exportTable(
            'ConversationMessage',
            "select distinct
                    t.pmtextid,
                    p.parentpmid as ConversationID,
                    t.message as Body,
                    'BBCode' as Format,
                    t.fromuserid as InsertUserID,
                    from_unixtime(t.dateline) as DateInserted
                from :_pmtext t
                join (
                    select pmtextid, parentpmid
                    from :_pm
                    where parentpmid > 0
                    group by pmtextid having count(pmtextid) > 1
                ) p on t.pmtextid = p.pmtextid"
        );

        // User Conversation.
        $ex->exportTable(
            'UserConversation',
            "
                select
                userid as UserID,
                parentpmid as ConversationID,
                messageread as CountReadMessages
                from :_pm
                where parentpmid > 0
            	group by userid, parentpmid"
        );
    }

    /**
     * @param ExportModel $ex
     * @return void
     */
    protected function ipbans(ExportModel $ex): void
    {
        $ipBanlist = $this->param('ipbanlist');
        if ($ipBanlist) {
            $ex->query("drop table if exists z_ipbanlist");
            $ex->query(
                "create table z_ipbanlist(
                    id int(11) unsigned not null auto_increment,
                    ipaddress varchar(50) default null,
                    primary key (id),
                    unique key ipaddress (ipaddress)
                ) engine=InnoDB default charset=utf8"
            );

            $result = $ex->query("select value from :_setting where varname = 'banip'");
            $row = $result->nextResultRow();

            if ($row) {
                $insertSql = 'insert ignore into z_ipbanlist(ipaddress) values ';
                $ipString = str_replace("\r", "", $row['value']);
                $IPs = explode(" ", $ipString);
                foreach ($IPs as $IP) {
                    $IP = trim($IP);
                    if (empty($IP)) {
                        continue;
                    }
                    $insertSql .= "({$ex->escape($IP)}), ";
                }
                $insertSql = substr($insertSql, 0, -2);
                $ex->query($insertSql);

                $ex->exportTable(
                    'Ban',
                    "select
                            'IPAddress' as BanType,
                            ipaddress as BanValue,
                            'Imported ban' as Notes,
                            NOW() as DateInserted
                        from z_ipbanlist"
                );
                //$ex->query('drop table if exists z_ipbanlist');
            }
        }
    }

    /**
     * @param ExportModel $ex
     * @return void
     */
    protected function reactions(ExportModel $ex): void
    {
        $ex->exportTable(
            'UserTag',
            "select
                    if(t.threadid is not null, 'Discussion', 'Comment') as RecordType,
                    if(t.threadid is not null, t.threadid, p.postid) as RecordID,
                    -1 as TagID,
                    p.userid as UserID,
                    from_unixtime(p.date) as DateInserted,
                    1 as Total
                from :_post_thanks p
                left join :_thread t on p.postid = t.firstpostid
                union
                select
                    concat(if(t.threadid is not null, 'Discussion', 'Comment'), '-Total') as RecordType,
                    if(t.threadid is not null, t.threadid, p.postid) as RecordID,
                    -1 as TagID,
                    p.userid as UserID,
                    now() as DateInserted,
                    p.total as Total
                from (select postid, count(postid) as total, min(userid) as userid from :_post_thanks group by postid) p
                left join :_thread t on p.postid = t.firstpostid"
        );
    }

    /**
     * @param string $forumWhere
     */
    protected function categories($ex, string $forumWhere): void
    {
        $category_Map = array(
            'title' => array('Column' => 'Name', 'Filter' => array($this, 'htmlDecode')),
            'displayorder' => array('Column' => 'Sort', 'Type' => 'int'),
        );
        $ex->exportTable(
            'Category',
            "select
                    f.forumid as CategoryID,
                    f.description as Description,
                    f.parentid as ParentCategoryID,
                    f.title,
                    f.displayorder
                from :_forum as f
                where 1 = 1
                $forumWhere",
            $category_Map
        );
    }

    protected function roles($ex): void
    {
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
        $ex->query("drop table if exists VbulletinRoles");
        $ex->query(
            "create table VbulletinRoles (
            userid int unsigned not null, usergroupid int unsigned not null)"
        );
        // Put primary groups into tmp table
        $ex->query("insert into VbulletinRoles (userid, usergroupid) select userid, usergroupid from :_user");
        // Put stupid CSV column into tmp table
        $secondaryRoles = $ex->query("select userid, usergroupid, membergroupids from :_user");
        if (is_resource($secondaryRoles)) {
            while ($row = $secondaryRoles->nextResultRow()) {
                if ($row['membergroupids'] != '') {
                    $groups = explode(',', $row['membergroupids']);
                    foreach ($groups as $groupID) {
                        if (!$groupID) {
                            continue;
                        }
                        $ex->query(
                            "insert into VbulletinRoles (userid, usergroupid)
                            values({$row['userid']},{$groupID})"
                        );
                    }
                }
            }
        }
        // Export from our tmp table and drop
        $ex->exportTable('UserRole', 'select distinct userid, usergroupid from VbulletinRoles', $userRole_Map);
        $ex->query("drop table if exists VbulletinRoles");
    }

    /**
     * @param array $ranks
     * @param $cdn
     */
    protected function users($ex, array $ranks, $cdn): void
    {
        $user_Map = array(
            'usertitle' => array(
                'Column' => 'Title',
                'Filter' => function ($value) {
                    return trim(strip_tags(str_replace('&nbsp;', ' ', $value)));
                }
            ),
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

        $ex->exportTable(
            'User',
            "select
                u.userid as UserID,
                u.username as Name,
                u.email as Email,
                u.referrerid as InviteUserID,
                u.timezoneoffset as HourOffset,
                u.timezoneoffset as HourOffset,
                u.ipaddress as LastIPAddress,
                u.ipaddress as InsertIPAddress,
                u.usertitle,
                u.posts,
                concat(`password`, salt) as Password,
                date_format(birthday_search, get_format(DATE, 'ISO')) as DateOfBirth,
                from_unixtime(joindate) as DateFirstVisit,
                from_unixtime(lastvisit) as DateLastActive,
                from_unixtime(joindate) as DateInserted,
                from_unixtime(lastactivity) as DateUpdated,
                case when avatarrevision > 0
                        then concat('$cdn', 'userpics/avatar', u.userid, '_', avatarrevision, '.gif')
                     when av.avatarpath is not null then av.avatarpath
                     else null
                     end as filephoto,
                {$this->avatarSelect},
                case when ub.userid is not null then 1 else 0 end as Banned,
                'vbulletin' as HashMethod
            from :_user u
                left join :_customavatar a on u.userid = a.userid
                left join :_avatar av on u.avatarid = av.avatarid
                left join :_userban ub on u.userid = ub.userid and ub.liftdate <= now()",
            $user_Map
        );
    }

    /**
     * @param string $forumWhere
     * @param $minDate
     */
    protected function userMeta($ex, string $forumWhere, $minDate): void
    {
        $ex->query("drop table if exists VbulletinUserMeta");
        $ex->query(
            "
            create table VbulletinUserMeta(
                `UserID` int not null,
                `Name` varchar(255) not null,
                `Value` text not null
            );
        "
        );
        // Standard vB user data
        $userFields = array(
            'usertitle' => 'Title',
            'homepage' => 'Website',
            'styleid' => 'StyleID'
        );

        if ($ex->exists('user', array('skype')) === true) {
            $userFields['skype'] = 'Skype';
        }

        foreach ($userFields as $field => $insertAs) {
            $ex->query(
                "insert into VbulletinUserMeta (UserID, Name, Value)
                    select
                        userid,
                        'Profile.$insertAs',
                        $field
                    from :_user where $field != '' and $field != 'http://'

                    union select userid as UserID,
                        concat('Preferences.Popup.NewComment.', forumid), 1 as Value
                        from :_subscribeforum
                    union select userid as UserID,
                        concat('Preferences.Popup.NewDiscussion.', forumid), 1 as Value
                        from :_subscribeforum
                    union select userid as UserID,
                        concat('Preferences.Email.NewComment.', forumid), 1 as Value
                        from :_subscribeforum where emailupdate > 1
                    union select userid as UserID,
                        concat('Preferences.Email.NewDiscussion.', forumid), 1 as Value
                        from :_subscribeforum where emailupdate > 1"
            );
        }

        if ($ex->exists('phrase', array('product', 'fieldname')) === true) {
            // Dynamic vB user data (userfield)
            $profileFields = $ex->query(
                "select distinct
                    varname,
                    text
                from :_phrase
                where product='vbulletin'
                    and fieldname='cprofilefield'
                    and varname like 'field%_title'"
            );

            if (is_resource($profileFields)) {
                $profileQueries = array();
                while ($field = $profileFields->nextResultRow()) {
                    $column = str_replace('_title', '', $field['varname']);
                    $name = preg_replace('/[^a-zA-Z0-9\s_-]/', '', $field['text']);
                    $profileQueries[] = "
                        insert into VbulletinUserMeta(UserID, Name, Value)
                        select
                            userid,
                            'Profile." . $name . "',
                            " . $column . "
                        from :_userfield
                        where " . $column . " != ''";
                }
                foreach ($profileQueries as $query) {
                    $ex->query($query);
                }
            }
        }

        // Users meta informations
        $ex->exportTable(
            'UserMeta',
            "select
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
                where nullif(signature, '') is not null
                union
                select * from VbulletinUserMeta"
        );
        $this->categories($ex, $forumWhere);

        if ($minDate) {
            $minDiscussionID = $ex->getValue(
                "select max(threadid)
                    from :_thread
                    where dateline < $minDate",
                false
            );

            $minDiscussionID2 = $ex->getValue(
                "select min(threadid)
                    from :_thread
                    where dateline >= $minDate",
                false
            );

            // The two discussion IDs should be the same, but let's average them.
            $minDiscussionID = floor(($minDiscussionID + $minDiscussionID2) / 2);

            $ex->comment('Min topic id: ' . $minDiscussionID);
        }
    }

    /**
     * @param $minDiscussionID
     * @param string $forumWhere
     * @return string
     */
    protected function comments($ex, $minDiscussionID, string $forumWhere): void
    {
        $comment_Map = array(
            'pagetext' => array('Column' => 'Body', 'Filter' => function ($value) {
                return $value;
            }
            ),
        );

        $minDiscussionWhere = '';
        if ($minDiscussionID) {
            $minDiscussionWhere = "and p.threadid > $minDiscussionID";
        }

        $ex->exportTable(
            'Comment',
            "select
                    p.postid as CommentID,
                    p.threadid as DiscussionID,
                    p.pagetext,
                    p.ipaddress as InsertIPAddress,
                    'BBCode' as Format,
                    p.userid as InsertUserID,
                    p.userid as UpdateUserID,
                    from_unixtime(p.dateline) as DateInserted
                from :_post as p
                    inner join :_thread as t on p.threadid = t.threadid
                    left join :_deletionlog as d on (d.type='post' and d.primaryid=p.postid)
                where p.postid <> t.firstpostid
                    and d.primaryid is null
                    and p.visible = 1
                    $minDiscussionWhere
                    $forumWhere",
            $comment_Map
        );
    }

    /**
     * @param $minDiscussionID
     */
    protected function wallPosts($ex, $minDiscussionID): void
    {
        // Activity (from visitor messages in vBulletin 3.8+)
        $minDiscussionWhere = '';
        if ($ex->exists('visitormessage') === true) {
            if ($minDiscussionID) {
                $minDiscussionWhere = "and dateline > $minDiscussionID";
            }

            $activity_Map = array(
                'postuserid' => 'RegardingUserID',
                'userid' => 'ActivityUserID',
                'pagetext' => 'Story',
                'NotifyUserID' => 'NotifyUserID',
                'Format' => 'Format'
            );
            $ex->exportTable(
                'Activity',
                "select
                    vm.*,
                    '{RegardingUserID,you} &rarr; {ActivityUserID,you}' as HeadlineFormat,
                    from_unixtime(vm.dateline) as DateInserted,
                    from_unixtime(vm.dateline) as DateUpdated,
                    inet_ntoa(vm.ipaddress) as InsertIPAddress,
                    vm.postuserid as InsertUserID,
                    -1 as NotifyUserID,
                    'BBCode' as Format,
                    'WallPost' as ActivityType
                from :_visitormessage as vm
                where state='visible'
                    $minDiscussionWhere",
                $activity_Map
            );
        }
    }

    /**
     * @param ExportModel $ex
     * @param $minDiscussionID
     * @param string $forumWhere
     */
    protected function discussions(ExportModel $ex, $minDiscussionID, string $forumWhere): void
    {
        $discussion_Map = array(
            'title' => array('Column' => 'Name', 'Filter' => array($this, 'htmlDecode')),
            'pagetext' => array('Column' => 'Body', 'Filter' => function ($value) {
                return $value;
            }
            ),
        );

        $minDiscussionWhere = '';
        if ($minDiscussionID) {
            $minDiscussionWhere = "and t.threadid > $minDiscussionID";
        }

        $ex->exportTable(
            'Discussion',
            "select
                    t.threadid as DiscussionID,
                    t.forumid as CategoryID,
                    t.postuserid as InsertUserID,
                    t.postuserid as UpdateUserID,
                    t.views as CountViews,
                    t.sticky as Announce,
                    t.title,
                    p.postid as ForeignID,
                    p.ipaddress as InsertIPAddress,
                    p.pagetext,
                    'BBCode' as Format,
                    replycount+1 as CountComments,
                    convert(ABS(open-1), char(1)) as Closed,
                    if(convert(sticky, char(1)) > 0, 2, 0) as Announce,
                    from_unixtime(t.dateline) as DateInserted,
                    from_unixtime(lastpost) as DateLastComment,
                    if (t.pollid > 0, 'Poll', null) as Type
                from :_thread as t
                    left join :_deletionlog as d on d.type='thread' and d.primaryid=t.threadid
                    left join :_post as p on p.postid = t.firstpostid
                where d.primaryid is null
                    and t.visible = 1
                $minDiscussionWhere
                $forumWhere",
            $discussion_Map
        );
    }

    /**
     * @return array
     */
    protected function permissions($ex): void
    {
        $permissions_Map = array(
            'usergroupid' => 'RoleID',
            'title' => array('Column' => 'Garden.SignIn.Allow', 'Filter' => array($this, 'signInPermission')),
            'genericpermissions' => array('Column' => 'GenericPermissions', 'type' => 'int'),
            'forumpermissions' => array('Column' => 'ForumPermissions', 'type' => 'int')
        );
        $this->addPermissionColumns(self::$permissions, $permissions_Map);
        $ex->exportTable('Permission', 'select * from :_usergroup', $permissions_Map);
    }
}
