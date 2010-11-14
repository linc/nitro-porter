<?php
/**
 * vBulletin exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
 
class Vbulletin extends ExportController {
   
   /** @var array Required tables => columns */
   protected $SourceTables = array(
      'user' => array('userid','username','password','email','referrerid','timezoneoffset','posts','salt',
         'birthday_search','joindate','lastvisit','lastactivity','membergroupids','usergroupid',
         'usertitle', 'homepage', 'aim', 'icq', 'yahoo', 'msn', 'skype', 'styleid', 'avatarid'),
      'usergroup'=> array('usergroupid','title','description'),
      'userfield' => array('userid'),
      'phrase' => array('varname','text','product','fieldname','varname'),
      'thread' => array('threadid','forumid','postuserid','title','open','sticky','dateline','lastpost'),
      'deletionlog' => array('type','primaryid'),
      'post' => array('postid','threadid','pagetext','userid','dateline'),
      'forum' => array('forumid','description','displayorder','title','description','displayorder'),
      'subscribethread' => array('userid','threadid')
   );
   
   /**
    * vBulletin-specific export format
    *
    * Avatars should be moved to the filesystem prior to export if they
    * are stored in the database. Copy all the avatar_* files from
    * vBulletin's /customavatars folder to Vanilla's /upload/userpics.
    */
   protected function ForumExport($Ex) {
      // Begin
      $Ex->BeginExport('', 'vBulletin 3.* and 4.*');
      
      // Users
      $User_Map = array(
         'userid'=>'UserID',
         'username'=>'Name',
         'password2'=>'Password',
         'email'=>'Email',
         'referrerid'=>'InviteUserID',
         'timezoneoffset'=>'HourOffset',
         'salt'=>'char(3)'
      );
      $Ex->ExportTable('User', "select *,
				concat(`password`, salt) as password2,
				concat('userpics/avatar', userid, '_', avatarrevision, '.gif') as Photo,
            DATE_FORMAT(birthday_search,GET_FORMAT(DATE,'ISO')) as DateOfBirth,
            FROM_UNIXTIME(joindate) as DateFirstVisit,
            FROM_UNIXTIME(lastvisit) as DateLastActive,
            FROM_UNIXTIME(joindate) as DateInserted,
            FROM_UNIXTIME(lastactivity) as DateUpdated
         from :_user", $User_Map);  // ":_" will be replace by database prefix
      
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
      
      // UserMeta
      $Ex->Query("CREATE TEMPORARY TABLE VbulletinUserMeta (`UserID` INT NOT NULL ,`MetaKey` VARCHAR( 64 ) NOT NULL ,`MetaValue` VARCHAR( 255 ) NOT NULL)");
      # Standard vB user data
      $UserFields = array('usertitle', 'homepage', 'aim', 'icq', 'yahoo', 'msn', 'skype', 'styleid');
      foreach($UserFields as $Field)
         $Ex->Query("insert into VbulletinUserMeta (UserID, MetaKey, MetaValue) select userid, '$Field.', $Field from :_user where $Field !=''");
      # Dynamic vB user data (userfield)
      $ProfileFields = $Ex->Query("select varname, text from :_phrase where product='vbulletin' and fieldname='cprofilefield' and varname like 'field%_title'");
      if (is_resource($ProfileFields)) {
         while (($Field = @mysql_fetch_assoc($ProfileFields)) !== false) {
         //foreach ($ProfileFields as $Field) {
            $VbulletinField = str_replace('_title','',$Field['varname']);
            $MetaKey = preg_replace('/[^0-9a-z_-]/','',strtolower($Field['text']));
            $Ex->Query("insert into VbulletinUserMeta (UserID, MetaKey, MetaValue)
               select userid, '".$MetaKey."', ".$VbulletinField." from :_userfield where ".$VbulletinField."!=''");
         }
      }
      # Export from our tmp table and drop
      $Ex->ExportTable('UserMeta', 'select UserID, MetaKey as Name, MetaValue as Value from VbulletinUserMeta');
      $Ex->Query("DROP TABLE IF EXISTS VbulletinUserMeta");
      
      // Categories
      $Category_Map = array(
         'forumid'=>'CategoryID',
         'description'=>'Description',
         'Name'=>array('Column'=>'Name','Filter'=>array($Ex, 'HTMLDecoder')),
         'displayorder'=>array('Column'=>'Sort', 'Type'=>'int')
      );
      $Ex->ExportTable('Category', "select forumid, left(title,30) as Name, description, displayorder
         from :_forum where threadcount > 0", $Category_Map);
      
      // Discussions
      $Discussion_Map = array(
         'threadid'=>'DiscussionID',
         'forumid'=>'CategoryID',
         'postuserid'=>'InsertUserID',
         'postuserid2'=>'UpdateUserID',
         'title'=>array('Column'=>'Name','Filter'=>array($Ex, 'HTMLDecoder')),
			'Format'=>'Format'
      );
      $Ex->ExportTable('Discussion', "select t.*,
				t.postuserid as postuserid2,
            p.pagetext as Body,
				'BBCode' as Format,
            replycount+1 as CountComments, 
            convert(ABS(open-1),char(1)) as Closed, 
            convert(sticky,char(1)) as Announce,
            FROM_UNIXTIME(t.dateline) as DateInserted,
            FROM_UNIXTIME(lastpost) as DateUpdated,
            FROM_UNIXTIME(lastpost) as DateLastComment
         from :_thread t
            left join :_deletionlog d ON (d.type='thread' AND d.primaryid=t.threadid)
				left join :_post p ON p.postid = t.firstpostid
         where d.primaryid IS NULL", $Discussion_Map);
      
      // Comments
      $Comment_Map = array(
         'postid' => 'CommentID',
         'threadid' => 'DiscussionID',
         'pagetext' => 'Body',
			'Format' => 'Format'
      );
      $Ex->ExportTable('Comment', "select p.*,
				'BBCode' as Format,
            p.userid as InsertUserID,
            p.userid as UpdateUserID,
            FROM_UNIXTIME(p.dateline) as DateInserted,
            FROM_UNIXTIME(p.dateline) as DateUpdated
         from :_post p
				inner join :_thread t ON p.threadid = t.threadid
            left join :_deletionlog d ON (d.type='post' AND d.primaryid=p.postid)
         where p.postid <> t.firstpostid and d.primaryid IS NULL", $Comment_Map);
      
      // UserDiscussion
		$UserDiscussion_Map = array(
			'DateLastViewed' =>  'datetime');
      $Ex->ExportTable('UserDiscussion', "select
           tr.userid as UserID,
           tr.threadid as DiscussionID,
           FROM_UNIXTIME(tr.readtime) as DateLastViewed,
           case when st.threadid is not null then 1 else 0 end as Bookmarked
         from :_threadread tr
         left join :_subscribethread st on tr.userid = st.userid and tr.threadid = st.threadid");
      
      // Activity (from visitor messages in vBulletin 3.8+)
      if ($Ex->Exists('visitormessage')) {
         $Activity_Map = array(
            'postuserid'=>'ActivityUserID',
            'userid'=>'RegardingUserID',
            'pagetext'=>'Story',
            'postuserid'=>'InsertUserID'
         );
         $Ex->ExportTable('Activity', "select *, 
   			   FROM_UNIXTIME(dateline) as DateInserted
   			from :_visitormessage
   			where state='visible'", $Activity_Map);
      }
      
      // Media
      if ($Ex->Exists('attachment')) {
         $Media_Map = array(
            'attachmentid' => 'MediaID',
            'filename' => 'Name',
            'extension' => 'Type',
            'filesize' => 'Size',
            'filehash' => array('Column' => 'Path', 'Filter' => array($this, 'BuildMediaPath')),
            'userid' => 'InsertUserID'
         );
         // Test if hash field exists from 2.x
         $SelectHash = '';
         if ($Ex->Exists('attachment', array('hash')))
            $SelectHash = 'a.hash,';
         
         // A) Do NOT grab every field to avoid potential 'filedata' blob.
         // B) We must left join 'attachment' because we can't left join 'thread' on firstpostid (not an index).
         
         // First comment attachments => 'Discussion' foreign key
         $Ex->ExportTable('Media',
            "select a.attachmentid, a.filename, a.extension, a.filesize, a.filehash, $SelectHash a.userid,
               'local' as StorageMethod, 
               'discussion' as ForeignTable,
               t.threadid as ForeignID,
               FROM_UNIXTIME(a.dateline) as DateInserted
            from :_thread t
               left join :_attachment a ON a.postid = t.firstpostid
            where a.attachmentid > 0", $Media_Map);
         
         // All other comment attachments => 'Comment' foreign key
         $Ex->ExportTable('Media',
            "select a.attachmentid, a.filename, a.extension, a.filesize, a.filehash, $SelectHash a.userid,
               'local' as StorageMethod, 
               'comment' as ForeignTable,
               a.postid as ForeignID,
               FROM_UNIXTIME(a.dateline) as DateInserted
            from :_post p
               inner join :_thread t ON p.threadid = t.threadid
               left join :_attachment a ON a.postid = p.postid
            where p.postid <> t.firstpostid and  a.attachmentid > 0", $Media_Map);
      }
      
      // End
      $Ex->EndExport();
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
         return '/uploads/'.$Row['hash'].'.file';//.$Row['extension'];
      }
      else { // Newer than 3.0
         // Build user directory path
         $n = strlen($Row['userid']);
         $DirParts = array();
         for($i = 0; $i < $n; $i++) {
            $DirParts[] = $Row['userid']{$i};
         }
         return '/uploads/'.implode('/', $DirParts).'/'.$Row['attachmentid'].'.attach';//.$Row['extension'];
      }
   }
   
}
?>