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
   protected $SourceTables = array(
      'user'=> array()
      );
   
   /**
    * Forum-specific export format
    */
   protected function ForumExport($Ex) {
      
      // Vanilla fields that will be populated by vBulletin
      $Ex->_Structures = array(
   		'Category' => array('CategoryID' => 'int', 'Name' => 'varchar(30)', 'Description' => 'varchar(250)', 'ParentCategoryID' => 'int', 'DateInserted' => 'datetime', 'InsertUserID' => 'int', 'DateUpdated' => 'datetime', 'UpdateUserID' => 'int'),
   		'Comment' => array('CommentID' => 'int', 'DiscussionID' => 'int', 'DateInserted' => 'datetime', 'InsertUserID' => 'int', 'DateUpdated' => 'datetime', 'UpdateUserID' => 'int', 'Format' => 'varchar(20)', 'Body' => 'text', 'Score' => 'float'),
   		'Discussion' => array('DiscussionID' => 'int', 'Name' => 'varchar(100)', 'CategoryID' => 'int', 'DateInserted' => 'datetime', 'InsertUserID' => 'int', 'DateUpdated' => 'datetime', 'UpdateUserID' => 'int', 'Score' => 'float', 'Closed' => 'tinyint', 'Announce' => 'tinyint'),
   		'Role' => array('RoleID' => 'int', 'Name' => 'varchar(100)', 'Description' => 'varchar(200)'),
   		'User' => array(
            'UserID' => 'int', 
            'Name' => 'varchar(20)', 
            'Email' => 'varchar(200)', 
            'Password' => 'varbinary(34)',
            'InviteUserID' => 'int',
            'HourOffset' => 'int',
            'CountComments' => 'int',
            'DateOfBirth' => 'datetime',
            'DateFirstVisit' => 'datetime',
            'DateLastActive' => 'datetime',
            'DateInserted' => 'datetime',
            'DateUpdated' => 'datetime',
            'CountDiscussions' => 'int',
            'VbulletinSalt' => 'varchar(8)'
            ),
		'UserRole' => array('UserID' => 'int', 'RoleID' => 'int')
		);
      
      
      
      // Direct field mapping
      $User_Map = array(
         'UserID'=>'userid',
         'Name'=>'username',
         'Password'=>'password',
         'Email'=>'email',
         'InviteUserID'=>'referrerid',
         'HourOffset'=>'timezoneoffset',
         'CountComments'=>'posts'
         );
         
         
      // Begin
      $Ex->BeginExport('export '.date('Y-m-d His').'.txt.gz', 'vBulletin 3+');   
      
      // Users   
      $Ex->ExportTable('User', "select *,
            DATE_FORMAT(birthday_search,GET_FORMAT(DATE,'ISO')) as DateOfBirth,
            FROM_UNIXTIME(joindate) as DateFirstVisit,
            FROM_UNIXTIME(lastvisit) as DateLastActive,
            FROM_UNIXTIME(joindate) as DateInserted,
            FROM_UNIXTIME(lastactivity) as DateUpdated,
            (SELECT COUNT(*) FROM ".$Ex->Prefix."thread WHERE postuserid=userid) as CountDiscussions,
            salt as VbulletinSalt
         from :_user", 
         $User_Map);  // ":_" will be replace by database prefix
      
      
      //$Ex->ExportTable('Role', 'select * from :_Role');
      //$Ex->ExportTable('UserRole', 'select * from :_UserRole');
      //$Ex->ExportTable('Category', 'select * from :_Category');
      //$Ex->ExportTable('Discussion', 'select * from :_Discussion');
      //$Ex->ExportTable('Comment', 'select * from :_Comment');
      
      // End
      $Ex->EndExport();
   }
   
}