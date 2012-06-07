<?php
/**
 * vBulletin exporter tool.
 * 
 * This will migrate all vBulletin data for 3.x and 4.x forums. It even 
 * accounts for attachments created during 2.x and moved to 3.x.
 *
 * Supports the FileUpload, ProfileExtender, and Signature plugins.
 * All vBulletin data appropriate for those plugins will be prepared
 * and transferred.
 *
 * MIGRATING FILES:
 * 
 * 1) Avatars should be moved to the filesystem prior to export if they
 * are stored in the database. Copy all the avatar_* files from
 * vBulletin's /customavatars folder to Vanilla's /upload/userpics.
 * IMPORTANT: Make /userpics writable by the server BEFORE IMPORTING.
 * 
 * 2) Attachments should likewise be moved to the filesystem prior to
 * export. Copy all attachments from vBulletin's attachments folder to 
 * Vanilla's /upload folder without changing the internal folder structure.
 * IMPORTANT: Enable the FileUpload plugin BEFORE IMPORTING.
 *
 * @copyright Vanilla Forums Inc. 2010
 * @author Matt Lincoln Russell lincoln@icrontic.com
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * vBulletin-specific extension of generic ExportController.
 *
 * @package VanillaPorter
 */
class Vbulletin extends ExportController {
   public $AttachSelect = "concat('/vbulletin/', left(f.filehash, 2), '/', f.filehash, '_', f.filedataid,'.', f.extension) as Path";
   public $AvatarSelect = "case when a.userid is not null then concat('customavatars/', a.userid % 100,'/avatar_', a.userid, right(a.filename, instr(reverse(a.filename), '.'))) else null end as customphoto";
   
   static $Permissions = array(
   
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
       8192 => 'Plugins.Attachments.Upload'),
   
   'adminpermissions' => array(
       1 => array('Garden.Moderation.Manage', 'Vanilla.Discussions.Announce', 'Vanilla.Discussions.Close', 'Vanilla.Discussions.Delete', 'Vanilla.Comments.Delete', 'Vanilla.Comments.Edit', 'Vanilla.Discussions.Edit', 'Vanilla.Discussions.Sink', 'Garden.Activity.Delete', 'Garden.Users.Add', 'Garden.Users.Edit', 'Garden.Users.Approve', 'Garden.Users.Delete', 'Garden.Applicants.Manage'),
       2 => array('Garden.Settings.View', 'Garden.Settings.Manage', 'Garden.Routes.Manage', 'Garden.Registration.Manage', 'Garden.Messages.Manage', 'Garden.Email.Manage', 'Vanilla.Categories.Manage', 'Vanilla.Settings.Manage', 'Vanilla.Spam.Manage', 'Garden.Plugins.Manage', 'Garden.Applications.Manage', 'Garden.Themes.Manage', 'Garden.Roles.Manage')
//       4 => 'Garden.Settings.Manage',),
       ),
   
   'wolpermissions' => array(
       16 => 'Plugins.WhosOnline.ViewHidden')
   );
   
   static $Permissions2 = array();
   
   /** @var array Required tables => columns */
   protected $SourceTables = array(
      'user' => array('userid','username','password','email','referrerid','timezoneoffset','posts','salt',
         'birthday_search','joindate','lastvisit','lastactivity','membergroupids','usergroupid',
         'usertitle', 'homepage', 'aim', 'icq', 'yahoo', 'msn', 'skype', 'styleid', 'avatarid'),
      'usergroup'=> array('usergroupid','title','description'),
      'userfield' => array('userid'),
      'phrase' => array('varname','text','product','fieldname','varname'),
      'thread' => array('threadid','forumid','postuserid','title','open','sticky','dateline','lastpost','visible'),
      'deletionlog' => array('type','primaryid'),
      'post' => array('postid','threadid','pagetext','userid','dateline','visible'),
      'forum' => array('forumid','description','displayorder','title','description','displayorder'),
      'subscribethread' => array('userid','threadid')
   );
   
   /**
    * Export each table one at a time.
    *
    * @param ExportModel $Ex
    */
   protected function ForumExport($Ex) {
      $ForumID = $this->Param('forumid');
      if ($ForumID)
         $ForumWhere = ' and t.forumid '.(strpos($ForumID, ', ') === FALSE ? "= $ForumID" : "in ($ForumID)");
      else
         $ForumWhere = '';
      
      $CharacterSet = $Ex->GetCharacterSet('post');
      if ($CharacterSet)
         $Ex->CharacterSet = $CharacterSet;
      
      $this->ExportBlobs(
         $this->Param('attachments'),
         $this->Param('avatars'),
         $ForumWhere
      );
      
      if ($this->Param('noexport'))
         return;
      
      // Begin
      $Ex->BeginExport('', 'vBulletin 3.* and 4.*');
      
      $Now = time();
      
//      $this->_ExportMedia();
//      $Ex->EndExport();
//      return;
  
      // Users
      $User_Map = array(
         'userid'=>'UserID',
         'username'=>'Name',
         'password2'=>'Password',
         'email'=>'Email',
         'referrerid'=>'InviteUserID',
         'timezoneoffset'=>'HourOffset',
         'salt'=>'char(3)',
         'ipaddress' => 'LastIPAddress'
      );
      
      if ($UseFieAvatar = $this->GetConfig('usefileavatar'))
         $User_Map['filephoto'] = 'Photo';
      else
         $User_Map['customphoto'] = 'Photo';
      
      $Ex->ExportTable('User', "select u.*,
				concat(`password`, salt) as password2,
            DATE_FORMAT(birthday_search,GET_FORMAT(DATE,'ISO')) as DateOfBirth,
            FROM_UNIXTIME(joindate) as DateFirstVisit,
            FROM_UNIXTIME(lastvisit) as DateLastActive,
            FROM_UNIXTIME(joindate) as DateInserted,
            FROM_UNIXTIME(lastactivity) as DateUpdated,
            case when avatarrevision > 0 then concat('userpics/avatar', u.userid, '_', 'avatarrevision', '.gif') else null end  as filephoto,
            {$this->AvatarSelect},
            case when ub.userid is not null then 1 else 0 end as Banned,
            'vbulletin' as HashMethod
         from :_user u
         left join :_customavatar a
         	on u.userid = a.userid
         left join :_userban ub
       	 	on u.userid = ub.userid and ub.liftdate <= now() ", $User_Map);  // ":_" will be replace by database prefix
      
      // Roles
      $Role_Map = array(
         'usergroupid'=>'RoleID',
         'title'=>'Name',
         'description'=>'Description'
      );   
      $Ex->ExportTable('Role', 'select * from :_usergroup', $Role_Map);
    
      // UserRoles
      $UserRole_Map = array(
         'userid'=>'UserID',
         'usergroupid'=>'RoleID'
      );
      $Ex->Query("CREATE TEMPORARY TABLE VbulletinRoles (userid INT UNSIGNED NOT NULL, usergroupid INT UNSIGNED NOT NULL)");
      # Put primary groups into tmp table
      $Ex->Query("insert into VbulletinRoles (userid, usergroupid) select userid, usergroupid from :_user");
      # Put stupid CSV column into tmp table
      $SecondaryRoles = $Ex->Query("select userid, usergroupid, membergroupids from :_user", TRUE);
      if (is_resource($SecondaryRoles)) {
         while (($Row = @mysql_fetch_assoc($SecondaryRoles)) !== false) {
            if($Row['membergroupids']!='') {
               $Groups = explode(',',$Row['membergroupids']);
               foreach($Groups as $GroupID) {
                  $Ex->Query("insert into VbulletinRoles (userid, usergroupid) values({$Row['userid']},{$GroupID})", TRUE);
               }
            }
         }
      }
      # Export from our tmp table and drop
      $Ex->ExportTable('UserRole', 'select distinct userid, usergroupid from VbulletinRoles', $UserRole_Map);
      $Ex->Query("DROP TABLE IF EXISTS VbulletinRoles");
      
      // Permissions.
      $Permissions_Map = array(
          'usergroupid' => 'RoleID',
          'title' => array('Column' => 'Garden.SignIn.Allow', 'Filter' => array($this, 'SignInPermission')),
          'genericpermissions' => array('Column' => 'GenericPermissions', 'type' => 'int'),
          'forumpermissions' => array('Column' => 'ForumPermissions', 'type' => 'int')
      );
      $this->AddPermissionColumns(self::$Permissions, $Permissions_Map);
      $Ex->ExportTable('Permission', 'select * from :_usergroup', $Permissions_Map);
      
//      $Ex->EndExport();
//      return;


      // UserMeta
      $Ex->Query("CREATE TEMPORARY TABLE VbulletinUserMeta (`UserID` INT NOT NULL ,`Name` VARCHAR( 64 ) NOT NULL ,`Value` VARCHAR( 255 ) NOT NULL)");
      # Standard vB user data
      $UserFields = array('usertitle' => 'Title', 'homepage' => 'Website', 'aim' => 'AIM', 'icq' => 'ICQ', 'yahoo' => 'Yahoo', 'msn' => 'MSN', 'skype' => 'Skype', 'styleid' => 'StyleID');
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
      }
      # Get signatures
      $Ex->Query("insert into VbulletinUserMeta (UserID, Name, Value) select userid, 'Sig', signatureparsed from :_sigparsed");
      # Export from our tmp table and drop
      $Ex->ExportTable('UserMeta', 'select * from VbulletinUserMeta');
      $Ex->Query("DROP TABLE IF EXISTS VbulletinUserMeta");

      // Categories
      $Category_Map = array(
         'forumid'=>'CategoryID',
         'description'=>'Description',
         'Name'=>array('Column'=>'Name'), //,'Filter'=>array($Ex, 'HTMLDecoder')),
         'displayorder'=>array('Column'=>'Sort', 'Type'=>'int'),
         'parentid'=>'ParentCategoryID'
      );
      $Ex->ExportTable('Category', "select f.*, left(title,30) as Name
         from :_forum f
         where 1 = 1 $ForumWhere", $Category_Map);
      
      // Discussions
      $Discussion_Map = array(
         'threadid'=>'DiscussionID',
         'forumid'=>'CategoryID',
         'postuserid'=>'InsertUserID',
         'postuserid2'=>'UpdateUserID',
         'title'=>array('Column'=>'Name','Filter'=>array($Ex, 'HTMLDecoder')),
			'Format'=>'Format',
         'views'=>'CountViews',
         'ipaddress' => 'InsertIPAddress'
      );
      
      if ($Ex->Destination == 'database') {
         // Remove the filter from the title so that this doesn't take too long.
         $Ex->HTMLDecoderDb('thread', 'title', 'threadid');
         unset($Discussion_Map['title']['Filter']);
      }
      
      $Ex->ExportTable('Discussion', "select t.*,
				t.postuserid as postuserid2,
            p.ipaddress,
            p.pagetext as Body,
				'BBCode' as Format,
            replycount+1 as CountComments, 
            convert(ABS(open-1),char(1)) as Closed, 
            convert(sticky,char(1)) as Announce,
            FROM_UNIXTIME(t.dateline) as DateInserted,
            FROM_UNIXTIME(lastpost) as DateUpdated,
            FROM_UNIXTIME(lastpost) as DateLastComment
         from :_thread t
            left join :_deletionlog d on (d.type='thread' and d.primaryid=t.threadid)
				left join :_post p on p.postid = t.firstpostid
         where d.primaryid is null
            and t.visible = 1
            $ForumWhere", $Discussion_Map);
      
      // Comments
      $Comment_Map = array(
         'postid' => 'CommentID',
         'threadid' => 'DiscussionID',
         'pagetext' => 'Body',
			'Format' => 'Format',
         'ipaddress' => 'InsertIPAddress'
      );
      $Ex->ExportTable('Comment', "select p.*,
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
            $ForumWhere", $Comment_Map);
      
      // UserDiscussion
      $Ex->ExportTable('UserDiscussion', "select 
            st.userid as UserID,
            st.threadid as DiscussionID,
            '1' as Bookmarked,
            FROM_UNIXTIME(tr.readtime) as DateLastViewed
         from :_subscribethread st
         left join :_threadread tr on tr.userid = st.userid and tr.threadid = st.threadid");
      /*$Ex->ExportTable('UserDiscussion', "select
           tr.userid as UserID,
           tr.threadid as DiscussionID,
           FROM_UNIXTIME(tr.readtime) as DateLastViewed,
           case when st.threadid is not null then 1 else 0 end as Bookmarked
         from :_threadread tr
         left join :_subscribethread st on tr.userid = st.userid and tr.threadid = st.threadid");*/
      
      // Activity (from visitor messages in vBulletin 3.8+)
      if ($Ex->Exists('visitormessage')) {
         $Activity_Map = array(
            'postuserid'=>'RegardingUserID',
            'userid'=>'ActivityUserID',
            'pagetext'=>'Story',
         );
         $Ex->ExportTable('Activity', "select *, 
               '{RegardingUserID,you} &rarr; {ActivityUserID,you}' as HeadlineFormat,
               FROM_UNIXTIME(dateline) as DateInserted,
               INET_NTOA(ipaddress) as InsertIPAddress,
               postuserid as InsertUserID,
               -1 as NotifiyUserID,
               'WallPost' as ActivityType
   			from :_visitormessage
   			where state='visible'", $Activity_Map);
      }

      // Massage PMs into Conversations.
      
      $Ex->Query('drop table if exists z_pmto');
      $Ex->Query('create table z_pmto (
        pmtextid int unsigned,
        userid int unsigned,
        primary key(pmtextid, userid)
      )');

      $Ex->Query('insert ignore z_pmto (
        pmtextid,
        userid
      )
      select
        pmtextid,
        userid
      from :_pm;');

      $Ex->Query('insert ignore z_pmto (
        pmtextid,
        userid
      )
      select
        pmtextid,
        fromuserid
      from :_pmtext;');

      $Ex->Query('insert ignore z_pmto (
        pmtextid,
        userid
      )
      select
        pm.pmtextid,
        r.userid
      from :_pm pm
      join :_pmreceipt r
        on pm.pmid = r.pmid;');

      $Ex->Query('insert ignore z_pmto (
        pmtextid,
        userid
      )
      select
        pm.pmtextid,
        r.touserid
      from :_pm pm
      join :_pmreceipt r
        on pm.pmid = r.pmid;');

      $Ex->Query('drop table if exists z_pmto2;');
      $Ex->Query('create table z_pmto2 (
        pmtextid int unsigned,
        userids varchar(250),
        primary key (pmtextid)
      );');

      $Ex->Query('insert z_pmto2 (
        pmtextid,
        userids
      )
      select
        pmtextid,
        group_concat(userid order by userid)
      from z_pmto t
      group by t.pmtextid;');

      $Ex->Query('drop table if exists z_pmtext;');
      $Ex->Query('create table z_pmtext (
        pmtextid int unsigned,
        title varchar(250),
        title2 varchar(250),
        userids varchar(250),
        group_id int unsigned
      );');

      $Ex->Query("insert z_pmtext (
        pmtextid,
        title,
        title2
      )
      select
        pmtextid,
        title,
        case when title like 'Re: %' then trim(substring(title, 4)) else title end as title2
      from :_pmtext pm;");

      $Ex->Query('create index z_idx_pmtext on z_pmtext (pmtextid);');

      $Ex->Query('update z_pmtext pm
      join z_pmto2 t
        on pm.pmtextid = t.pmtextid
      set pm.userids = t.userids;');

      // A conversation is a group of pmtexts with the same title and same users.

      $Ex->Query('drop table if exists z_pmgroup;');
      $Ex->Query('create table z_pmgroup (
        group_id int unsigned,
        title varchar(250),
        userids varchar(250)
      );');

      $Ex->Query('insert z_pmgroup (
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
      group by pm.title2, t2.userids;');

      $Ex->Query('create index z_idx_pmgroup on z_pmgroup (title, userids);');
      $Ex->Query('create index z_idx_pmgroup2 on z_pmgroup (group_id);');

      $Ex->Query('update z_pmtext pm
      join z_pmgroup g
        on pm.title2 = g.title and pm.userids = g.userids
      set pm.group_id = g.group_id;');

      // Conversations.
      $Conversation_Map = array(
         'pmtextid' => 'ConversationID',
         'fromuserid' => 'InsertUserID',
         'title2' => array('Column' => 'Subject', 'Type' => 'varchar(250)')
      );
      $Ex->ExportTable('Conversation', 
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
      $Ex->ExportTable('ConversationMessage',
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
      $Ex->ExportTable('UserConversation',
      "select
         g.group_id,
         t.userid
       from z_pmto t
       join z_pmgroup g
         on g.group_id = t.pmtextid;", $UserConversation_Map);

      $Ex->Query('drop table if exists z_pmto');
      $Ex->Query('drop table if exists z_pmto2;');
      $Ex->Query('drop table if exists z_pmtext;');
      $Ex->Query('drop table if exists z_pmgroup;');
      
      // Media
      if ($Ex->Exists('attachment')) {
         $this->_ExportMedia();
      }
      
      // End
      $Ex->EndExport();
      
      
   }
   
   function ExportBlobs($Attachments = TRUE, $CustomAvatars = TRUE) {
      $Ex = $this->Ex;
      
      if ($Attachments) {
         $Sql = "select 
            f.filedata, 
            {$this->AttachSelect}
            from :_filedata f";
         $Ex->ExportBlobs($Sql, 'filedata', 'Path');
      }
      
      if ($CustomAvatars) {
         $Sql = "select 
               a.filedata, 
               {$this->AvatarSelect}
            from :_customavatar a
            ";
         $Sql = str_replace('u.userid', 'a.userid', $Sql);
         $Ex->ExportBlobs($Sql, 'filedata', 'customphoto', 80);
      }
      
   }
   
   function _ExportMedia() {
      $Ex = $this->Ex;
      
      if ($Ex->Exists('attachment', array('contenttypeid', 'contentid')) === TRUE) {
         $Ex->Query('create index ix_thread_firstpostid on :_thread (firstpostid)');
         
         $Media_Map = array(
             'attachmentid' => 'MediaID',
             'filename' => 'Name',
             'filesize' => 'Size',
             'width' => 'ImageWidth',
             'height' => 'ImageHeight',
             'userid' => 'InsertUserID'
             );
         
         $Ex->ExportTable('Media', "select 
            case when t.threadid is not null then 'discussion' when ct.class = 'Post' then 'comment' when ct.class = 'Thread' then 'discussion' else ct.class end as ForeignTable,
            case when t.threadid is not null then t.threadid else a.contenttypeid end as ForeignID,
            {$this->AttachSelect},
            concat('image/', f.extension) as Type,
            FROM_UNIXTIME(a.dateline) as DateInserted,
            'local' as StorageMethod,
            a.*,
            f.extension, f.filesize,
            f.width, f.height
         from :_attachment a
         join :_contenttype ct
            on a.contenttypeid = ct.contenttypeid
         join :_filedata f
            on f.filedataid = a.filedataid
         left join :_thread t
            on t.firstpostid = a.contentid and a.contenttypeid = 1
         where a.contentid > 0", $Media_Map);
      } else {
         $this->_ExportMediaOld();
      }
   }
   
   function _ExportMediaOld() {   
      $Ex = $this->Ex;
      
      $Media_Map = array(
         'attachmentid' => 'MediaID',
         'filename' => 'Name',
         'extension' => array('Column' => 'Type', 'Filter' => array($this, 'BuildMimeType')),
         'filesize' => 'Size',
         'filehash' => array('Column' => 'Path', 'Filter' => array($this, 'BuildMediaPath')),
         'userid' => 'InsertUserID'
      );
      // Test if hash field exists from 2.x
      $AttachColumns = array('hash', 'filehash', 'filesize');
      $Missing = $Ex->Exists('attachment', $AttachColumns);
      $AttachColumnsString = '';
      foreach ($AttachColumns as $ColumnName) {
         if (in_array($ColumnName, $Missing)) {
            $AttachColumnsString .= ", null as $ColumnName";
         } else {
            $AttachColumnsString .= ", a.$ColumnName";
         }
      }

      // A) Do NOT grab every field to avoid potential 'filedata' blob.
      // B) We must left join 'attachment' because we can't left join 'thread' on firstpostid (not an index).
      // C) We lie about the height & width to spoof FileUpload serving generic thumbnail if they aren't set.

      // First comment attachments => 'Discussion' foreign key
      $Extension = ExportModel::FileExtension('a.filename');
      $Ex->ExportTable('Media',
         "select a.attachmentid, a.filename, $Extension as extension $AttachColumnsString, a.userid,
            'local' as StorageMethod, 
            'discussion' as ForeignTable,
            t.threadid as ForeignID,
            FROM_UNIXTIME(a.dateline) as DateInserted,
            '1' as ImageHeight,
            '1' as ImageWidth
         from :_thread t
            left join :_attachment a ON a.postid = t.firstpostid
         where a.attachmentid > 0

         union all

         select a.attachmentid, a.filename, $Extension $AttachColumnsString a.userid,
            'local' as StorageMethod, 
            'comment' as ForeignTable,
            a.postid as ForeignID,
            FROM_UNIXTIME(a.dateline) as DateInserted,
            '1' as ImageHeight,
            '1' as ImageWidth
         from :_post p
            inner join :_thread t ON p.threadid = t.threadid
            left join :_attachment a ON a.postid = p.postid
         where p.postid <> t.firstpostid and  a.attachmentid > 0
         ", $Media_Map);
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
    * changed when upgrading to 3.x so older forums will need those too.
    *
    * This assumes the user is going to copy their entire attachments directory
    * into Vanilla's /uploads folder and then use our custom plugin to convert
    * file extensions.
    *
    * @access public
    * @see ExportModel::_ExportTable
    * 
    * @param string $Value Ignored.
    * @param string $Field Ignored.
    * @param array $Row Contents of the current attachment record.
    * @return string Future path to file.
    */
   function BuildMediaPath($Value, $Field, $Row) {
      if (isset($Row['hash']) && $Row['hash'] != '') { 
         // Old school! (2.x)
         return $Row['hash'].'.file';//.$Row['extension'];
      }
      else { // Newer than 3.0
         // Build user directory path
         $n = strlen($Row['userid']);
         $DirParts = array();
         for($i = 0; $i < $n; $i++) {
            $DirParts[] = $Row['userid']{$i};
         }
         return implode('/', $DirParts).'/'.$Row['attachmentid'].'.attach';//.$Row['extension'];
      }
   }
   
   /**
    * Set valid MIME type for images.
    * 
    * @access public
    * @see ExportModel::_ExportTable
    * 
    * @param string $Value Extension from vBulletin.
    * @param string $Field Ignored.
    * @param array $Row Ignored.
    * @return string Extension or accurate MIME type.
    */
   function BuildMimeType($Value, $Field, $Row) {
      switch ($Value) {
         case 'jpg':
         case 'gif':
         case 'png':
            $Value = 'image/'.$Value;
            break;
      }
      return $Value;
   }
   
   /**
    * Create Photo path from avatar data.
    * 
    * @access public
    * @see ExportModel::_ExportTable
    * 
    * @param string $Value Ignored.
    * @param string $Field Ignored.
    * @param array $Row Contents of the current attachment record.
    * @return string Path to avatar if one exists, or blank if none.
    */
//   function BuildAvatar($Value, $Field, $Row) {
//      if ($Row['avatarrevision'] > 0)
//         return 'userpics/avatar' . $Row['userid'] . '_' . $Row['avatarrevision'] . '.gif';
//      else
//         return '';
//   }
   
   function SignInPermission($Value, $Field, $Row) {
      $Result = TRUE;
      if (stripos($Row['title'], 'unregistered') !== FALSE)
         $Result = FALSE;
      elseif (stripos($Row['title'], 'banned') !== FALSE)
         $Result = FALSE;
      
      return $Result;
   }
   
   function GetConfig($Name) {
      $Sql = "select * from :_setting where varname = 'usefileavatar'";
      $Result = $this->Ex->Query($Sql, TRUE);
      if ($Row = mysql_fetch_assoc($Result)) {
         return $Row['value'];
      }
      return FALSE;
   }
   
   function FilterPermissions($Value, $Field, $Row) {
      if (!isset(self::$Permissions2[$Field]))
         return 0;
      
      $Column = self::$Permissions2[$Field][0];
      $Mask = self::$Permissions2[$Field][1];
      
      $Value = ($Row[$Column] & $Mask) == $Mask;
      return $Value;
   }
   
   function AddPermissionColumns($ColumnGroups, &$Map) {
      $Permissions2 = array();
      
      foreach ($ColumnGroups as $ColumnGroup => $Columns) {
         foreach ($Columns as $Mask => $ColumnArray) {
            $ColumnArray = (array)$ColumnArray;
            foreach ($ColumnArray as $Column) {
               $Map[$Column] = array('Column' => $Column, 'Type' => 'tinyint(1)', 'Filter' => array($this, 'FilterPermissions'));
               
               $Permissions2[$Column] = array($ColumnGroup, $Mask);
            }
         }
      }
      self::$Permissions2 = $Permissions2;
   }
}
?>