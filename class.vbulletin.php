<?php
/**
 * vBulletin-specific exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
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
         'ImportSalt'=>'salt'
      );   
      $Ex->ExportTable('User', "select *,
            DATE_FORMAT(birthday_search,GET_FORMAT(DATE,'ISO')) as DateOfBirth,
            FROM_UNIXTIME(joindate) as DateFirstVisit,
            FROM_UNIXTIME(lastvisit) as DateLastActive,
            FROM_UNIXTIME(joindate) as DateInserted,
            FROM_UNIXTIME(lastactivity) as DateUpdated,
            (SELECT COUNT(*) FROM :_thread WHERE postuserid=userid) as CountDiscussions
         from :_user", $User_Map);  // ":_" will be replace by database prefix
      
      
      // Roles
      $Role_Map = array(
         'RoleID'=>'usergroupid',
         'Name'=>'title',
         'Description'=>'description'
      );   
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
      foreach($UserFields as $UF)
         $Ex->Query("insert into VbulletinUserMeta (UserID, MetaKey, MetaValue) select userid, '".$UF."', ".$UF." from :_user where ".$UF."!=''");
      # Dynamic vB user data (userfield)
      $Fields = $Ex->Query("select varname, text from :_phrase where product='vbulletin' and fieldname='cprofilefield' and varname like 'field%_title'");
      foreach ($Fields as $Field) {
         $VbulletinField = str_replace('_title','',$Field['varname']);
         $MetaKey = preg_replace('/[^0-9a-z_-]/','',strtolower($Field['text']));
         $Ex->Query("insert into VbulletinUserMeta (UserID, MetaKey, MetaValue) 
            select userid, '".$MetaKey."', ".$VbulletinField." from :_userfield where ".$VbulletinField."!=''");
      }
      # Export from our tmp table and drop
      $Ex->ExportTable('UserMeta', 'select UserID, MetaKey, MetaValue from VbulletinUserMeta');
      $Ex->Query("DROP TABLE VbulletinUserMeta");

      
      // Categories
      //$Ex->ExportTable('Category', 'select * from :_Category');
      
      // Discussions
      //$Ex->ExportTable('Discussion', 'select * from :_Discussion');
      
      // Comments
      //$Ex->ExportTable('Comment', 'select * from :_Comment');
      
      // Activity
      //$Ex->ExportTable('Activity', 'select * from :_Activity');
      
      // End
      $Ex->EndExport();
      
      PageFooter();
   }
   
}