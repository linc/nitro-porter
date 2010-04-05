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
         from :_user", 
         $User_Map);  // ":_" will be replace by database prefix
      
      
      // Roles
      $Role_Map = array(
         'RoleID'=>'usergroupid',
         'Name'=>'title',
         'Description'=>'description'
      );   
      $Ex->ExportTable('Role', 'select * from :_usergroup');
      
      
      // UserRoles (primary)
      $UserRole_Map = array(
         'UserID' => 'userid', 
         'RoleID'=> 'usergroupid'
      );
      $Ex->ExportTable('UserRole', 'select userid, usergroupid from :_user', $UserRole_Map);
      

      // UserRoles (secondary)
      $Ex->Query("CREATE TEMPORARY TABLE VbulletinSecondaryRoles (userid INT UNSIGNED NOT NULL, usergroupid INT UNSIGNED NOT NULL)");
      # Put stupid CSV column into tmp table
      $SecondaryRoles = $Ex->Query("select userid, membergroupids from :_user");
      foreach($SecondaryRoles as $Row) {
         if($Row['membergroupids']!='') {
            $Groups = explode(',',$Row['membergroupids']);
            foreach($Groups as $GroupID) {                  
               $Ex->Query("insert into VbulletinSecondaryRoles (userid, usergroupid) values(".$Row['userid'].",".$GroupID."");
            }
         }
      }
      # Export from our tmp table and drop
      $Ex->ExportTable('UserRole', 'select userid, usergroupid from VbulletinSecondaryRoles', $UserRole_Map);
      $Ex->Query("DROP TABLE VbulletinSecondaryRoles");

      
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