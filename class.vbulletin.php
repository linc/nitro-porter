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
            (SELECT COUNT(*) FROM ".$Ex->Prefix."thread WHERE postuserid=userid) as CountDiscussions
         from :_user", 
         $User_Map);  // ":_" will be replace by database prefix
      
      
      //$Ex->ExportTable('Role', 'select * from :_Role');
      //$Ex->ExportTable('UserRole', 'select * from :_UserRole');
      //$Ex->ExportTable('Category', 'select * from :_Category');
      //$Ex->ExportTable('Discussion', 'select * from :_Discussion');
      //$Ex->ExportTable('Comment', 'select * from :_Comment');
      
      // End
      $Ex->EndExport();
      
      PageFooter();
   }
   
}