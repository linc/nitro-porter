<?php
/**
 * vBulletin-specific exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 * @todo importer: html_entity_decode Category names and Discussion titles
 * @todo importer: count bookmarks, bookmark comment count
 * @todo importer: update Discussions with first & last comment ids
 * @todo importer: update CountDiscussions column on the Category, User tables
 * @todo importer: don't make ALL discussions "new" after import
 */
 
class Vbulletin extends ExportController {
   
   /** @var array Required tables => columns for vBulletin import */  
   protected $_SourceTables = array(
      'user'=> array()
      );
   
   /**
    * Forum-specific export format
    * @todo Project file size / export time and possibly break into multiple files
    */
   protected function ForumExport($Ex) {
      // Begin
      PageHeader();
      $Ex->BeginExport('export '.date('Y-m-d His').'.txt.gz', 'vBulletin 3+');   
      
      
      // Users
      $User_Map = array(
         'UserID'=>'userid',
         'Name'=>'username',
         'Password'=>'password',
         'Email'=>'email',
         'InviteUserID'=>'referrerid',
         'HourOffset'=>'timezoneoffset',
         'CountComments'=>'posts',
         'Salt'=>'salt'
      );   
      $Ex->ExportTable('User', "select *,
            DATE_FORMAT(birthday_search,GET_FORMAT(DATE,'ISO')) as DateOfBirth,
            FROM_UNIXTIME(joindate) as DateFirstVisit,
            FROM_UNIXTIME(lastvisit) as DateLastActive,
            FROM_UNIXTIME(joindate) as DateInserted,
            FROM_UNIXTIME(lastactivity) as DateUpdated
         from :_user", $User_Map);  // ":_" will be replace by database prefix
      
      
      // Roles
      $Role_Map = array(
         'RoleID'=>'usergroupid',
         'Name'=>'title',
         'Description'=>'description'
      ); 
      # Check number of roles (V2 has 32-role limit)
      $NumRoles = $Ex->Query("select COUNT(usergroupid) as TotalRoles from :_usergroup");
      foreach($NumRoles as $Row) {
         $TotalRoles = $Row['TotalRoles'];
      }
      if($TotalRoles > 32)
         $Ex->Comment('WARNING: Only 32 usergroups may be used in Vanilla 2. Some of your roles will be lost.');
      $Ex->ExportTable('Role', 'select * from :_usergroup', $Role_Map);
  
  
      // UserRoles
      $UserRole_Map = array(
         'UserID' => 'userid', 
         'RoleID'=> 'usergroupid'
      );
      $Ex->Query("CREATE TEMPORARY TABLE VbulletinRoles (userid INT UNSIGNED NOT NULL, usergroupid INT UNSIGNED NOT NULL)");
      # Put primary groups into tmp table
      $Ex->Query("insert into VbulletinRoles (userid, usergroupid) select userid, usergroupid from :_user");
      # Put stupid CSV column into tmp table
      $SecondaryRoles = $Ex->Query("select userid, membergroupids from :_user");
      foreach($SecondaryRoles as $Row) {
         if($Row['membergroupids']!='') {
            $Groups = explode(',',$Row['membergroupids']);
            foreach($Groups as $GroupID) {                  
               $Ex->Query("insert into VbulletinRoles (userid, usergroupid) values(".$Row['userid'].",".$GroupID."");
            }
         }
      }
      # Export from our tmp table and drop
      $Ex->ExportTable('UserRole', 'select userid, usergroupid from VbulletinRoles', $UserRole_Map);
      $Ex->Query("DROP TABLE VbulletinRoles");

      
      // UserMeta
      $Ex->Query("CREATE TEMPORARY TABLE VbulletinUserMeta (`UserID` INT NOT NULL ,`MetaKey` VARCHAR( 64 ) NOT NULL ,`MetaValue` VARCHAR( 255 ) NOT NULL)");
      # Standard vB user data
      $UserFields = array('usertitle', 'homepage', 'aim', 'icq', 'yahoo', 'msn', 'skype', 'styleid');
      foreach($UserFields as $Field)
         $Ex->Query("insert into VbulletinUserMeta (UserID, MetaKey, MetaValue) select userid, '".$Field."', ".$Field." from :_user where ".$Field."!=''");
      # Dynamic vB user data (userfield)
      $ProfileFields = $Ex->Query("select varname, text from :_phrase where product='vbulletin' and fieldname='cprofilefield' and varname like 'field%_title'");
      foreach ($ProfileFields as $Field) {
         $VbulletinField = str_replace('_title','',$Field['varname']);
         $MetaKey = preg_replace('/[^0-9a-z_-]/','',strtolower($Field['text']));
         $Ex->Query("insert into VbulletinUserMeta (UserID, MetaKey, MetaValue) 
            select userid, '".$MetaKey."', ".$VbulletinField." from :_userfield where ".$VbulletinField."!=''");
      }
      # Export from our tmp table and drop
      $Ex->ExportTable('UserMeta', 'select UserID, MetaKey, MetaValue from VbulletinUserMeta');
      $Ex->Query("DROP TABLE VbulletinUserMeta");

      
      // Categories
      $Category_Map = array(
         'CategoryID' => 'forumid', 
         'Description'=> 'description', 
         'Sort'=> 'displayorder'
      );
      $Ex->ExportTable('Category', "select forumid, left(title,30) as Name, description, displayorder
         from :_forum where threadcount > 0", $Category_Map);

      
      // Discussions
      $Discussion_Map = array(
         'DiscussionID' => 'threadid', 
         'CategoryID'=> 'forumid', 
         'InsertUserID'=> 'postuserid', 
         'UpdateUserID'=> 'postuserid', 
         'Name'=> 'title'
      );
      $Ex->ExportTable('Discussion', "select *, 
            replycount+1 as CountComments, 
            convert(ABS(open-1),char(1)) as Closed, 
            convert(sticky,char(1)) as Announce,
            FROM_UNIXTIME(t.dateline) as DateInserted,
            FROM_UNIXTIME(lastpost) as DateUpdated,
            FROM_UNIXTIME(lastpost) as DateLastComment
         from :_thread t
            left join :_deletionlog d ON (d.type='thread' AND d.primaryid=t.threadid)
         where d.primaryid IS NULL", $Discussion_Map);

      
      // Comments
      /*$Comment_Map = array(
         'CommentID' => 'postid', 
         'DiscussionID'=> 'threadid', 
         'Body'=> 'pagetext'
      );
      $Ex->ExportTable('Comment', "select *,
            p.userid as InsertUserID,
            p.userid as UpdateUserID,
            FROM_UNIXTIME(p.dateline) as DateInserted,
            FROM_UNIXTIME(p.dateline) as DateUpdated
         from :_post p
            left join :_deletionlog d ON (d.type='post' AND d.primaryid=p.postid)
         where d.primaryid IS NULL", $Comment_Map);
      */
      
      // UserDiscussion
      $Ex->ExportTable('UserDiscussion', "select userid as UserID, threadid as DiscussionID from :_subscribethread");

      
      // Activity (3.8+)
      $Activity_Map = array(
         'ActivityUserID' => 'postuserid', 
         'RegardingUserID'=> 'userid', 
         'Story'=> 'pagetext',
         'InsertUserID'=> 'postuserid'
      );
		$Tables = $Ex->Query("show tables like ':_visitormessage'");
      if (count($Tables) > 0) { # Table is present
			$Ex->ExportTable('Activity', "select *, 
			   FROM_UNIXTIME(dateline) as DateInserted
			from :_visitormessage
			where state='visible'", $Activity_Map);
      }

      
      // End
      $Ex->EndExport();
      
      PageFooter();
   }
   
}