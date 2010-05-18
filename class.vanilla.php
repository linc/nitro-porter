<?php

class Vanilla extends ExportController {

   /** @var array Required tables => columns for vBulletin import */  
   protected $_SourceTables = array(
      'user'=> array()
      );
   
   /**
    * Forum-specific export format
    * @todo Project file size / export time and possibly break into multiple files
    * 
    */
   protected function ForumExport($Ex) {
      // Begin
      $Ex->BeginExport('', 'Vanilla 1.x');
      
      // Users
      $User_Map = array(
         'UserID'=>'UserID',
         'Name'=>'Name',
         'Password'=>'Password',
         'Email'=>'Email',
         'CountComments'=>'CountComments'
      );   
      $Ex->ExportTable('User', "SELECT * FROM :_User", $User_Map);  // ":_" will be replaced by database prefix
      
      // Roles
      /*
		    'RoleID' => 'int', 
		    'Name' => 'varchar(100)', 
		    'Description' => 'varchar(200)'
		 */
      $Role_Map = array(
         'RoleID'=>'RoleID',
         'Name'=>'Name',
         'Description'=>'Description'
      );   
      $Ex->ExportTable('Role', 'select * from :_Role', $Role_Map);
  

      // UserRoles
      /*
		    'UserID' => 'int', 
		    'RoleID' => 'int'
		 */
      $UserRole_Map = array(
         'UserID' => 'UserID', 
         'RoleID'=> 'RoleID'
      );
      $Ex->ExportTable('UserRole', 'select UserID, RoleID from :_User', $UserRole_Map);
      
      // Categories
      /*
          'CategoryID' => 'int', 
          'Name' => 'varchar(30)', 
          'Description' => 'varchar(250)', 
          'ParentCategoryID' => 'int', 
          'DateInserted' => 'datetime', 
          'InsertUserID' => 'int', 
          'DateUpdated' => 'datetime', 
          'UpdateUserID' => 'int'
		 */
      $Category_Map = array(
         'CategoryID' => 'CategoryID', 
         'Name' => 'Name',
         'Description'=> 'Description'
      );
      $Ex->ExportTable('Category', "select CategoryID, Name, Description from :_Category", $Category_Map);

      
      // Discussions
      /*
		    'DiscussionID' => 'int', 
		    'Name' => 'varchar(100)', 
		    'CategoryID' => 'int', 
		    'Body' => 'text', 
		    'Format' => 'varchar(20)', 
		    'DateInserted' => 'datetime', 
		    'InsertUserID' => 'int', 
		    'DateUpdated' => 'datetime', 
		    'UpdateUserID' => 'int', 
		    'Score' => 'float', 
		    'Announce' => 'tinyint', 
		    'Closed' => 'tinyint'
		 */
      $Discussion_Map = array(
         'DiscussionID' => 'DiscussionID', 
         'Name' => 'Name',
         'CategoryID'=> 'CategoryID', 
         'Body'=> 'Body',
         'DateCreated'=>'DateInserted',
         'AuthUserID'=>'InsertUserID',
         'DateLastActive'=>'DateUpdated',
         'LastUserID'=>'UpdateUserID',
         'Closed'=>'Closed',
      );
      $Ex->ExportTable('Discussion', "
         SELECT d.*,c.Body FROM :_Discussion d
         LEFT JOIN :_Comment c ON (c.CommentID = d.FirstCommentID)", $Discussion_Map);
      
      // Comments
      /*
		    'CommentID' => 'int', 
		    'DiscussionID' => 'int', 
		    'DateInserted' => 'datetime', 
		    'InsertUserID' => 'int', 
		    'DateUpdated' => 'datetime', 
		    'UpdateUserID' => 'int', 
		    'Format' => 'varchar(20)', 
		    'Body' => 'text', 
		    'Score' => 'float'
		 */
      $Comment_Map = array(
         'CommentID' => 'CommentID',
         'DiscussionID' => 'DiscussionID',
         'AuthUserID' => 'InsertUserID',
         'DateCreated' => 'DateInserted',
         'EditUserID' => 'UpdateUserID',
         'DateEdited' => 'DateUpdated',
         'Body' => 'Body'
      );
      $Ex->ExportTable('Comment', "
         SELECT * FROM :_Comment c
         WHERE c.WhisperUserID = 0", $Comment_Map);
      
      // Conversations
      /*
          'ConversationID' => 'int', 
          'FirstMessageID' => 'int', 
          'DateInserted' => 'datetime', 
          'InsertUserID' => 'int', 
          'DateUpdated' => 'datetime', 
          'UpdateUserID' => 'int'
      */
      $Conversation_Map = array(
         'DiscussionID' => 'ConversationID',
         'AuthUserID' => 'InsertUserID',
         'DateCreated' => 'DateInserted',
         'EditUserID' => 'UpdateUserID',
         'DateEdited' => 'DateUpdated'
      );
      $Ex->ExportTable('Conversation', "SELECT DISTINCT DiscussionID, AuthUserID, DateCreated, EditUserID, DateEdited 
         FROM :_Comment c
         WHERE c.WhisperUserID > 0
         GROUP BY DiscussionID", $Conversation_Map);
      
      // ConversationMessage
      /*
         'MessageID' => 'int', 
         'ConversationID' => 'int', 
         'Body' => 'text', 
         'InsertUserID' => 'int', 
         'DateInserted' => 'datetime'
      */
      $ConversationMessage_Map = array(
         'CommentID' => 'MessageID',
         'DiscussionID' => 'ConversationID',
         'Body' => 'Body',
         'AuthUserID' => 'InsertUserID',
         'DateCreated' => 'DateInserted'
      );
      $Ex->ExportTable('ConversationMessage', "
         SELECT CommentID, DiscussionID, AuthUserID, DateCreated, Body FROM :_Comment c
         WHERE c.WhisperUserID > 0", $ConversationMessage_Map);
      
      // UserConversation
      $Ex->Query("CREATE TEMPORARY TABLE VanillaExportUserConversations (`UserID` INT NOT NULL ,`ConversationID` INT NOT NULL)");
      $Ex->Query("
            INSERT INTO VanillaExportUserConversations (ConversationID, UserID) 
            SELECT DISTINCT DiscussionID AS ConversationID, AuthUserID AS UserID FROM :_Comment 
            WHERE WhisperUserID > 0
            GROUP BY DiscussionID");
      $Ex->Query("
            INSERT INTO VanillaExportUserConversations (ConversationID, UserID) 
            SELECT DISTINCT DiscussionID AS ConversationID, WhisperUserID AS UserID FROM :_Comment
            WHERE WhisperUserID > 0
            GROUP BY DiscussionID");
      /*
         'UserID' => 'int', 
         'ConversationID' => 'int', 
         'LastMessageID' => 'int'
      */
      $UserConversation_Map = array(
         'UserID' => 'UserID',
         'ConversationID' => 'ConversationID'
      );
      $Ex->ExportTable('UserConversation', "SELECT ConversationID, UserID FROM VanillaExportUserConversations", $UserConversation_Map);
         
      // End
      $Ex->EndExport();
   }

}
?>
