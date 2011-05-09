<?php
/**
 * Vanilla 1 exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

class Vanilla1 extends ExportController {

   /** @var array Required tables => columns */  
   public $SourceTables = array(
      'User'=> array('UserID', 'Name', 'Password', 'Email', 'CountComments'),
      'Role'=> array('RoleID', 'Name', 'Description'),
      'Category'=> array('CategoryID', 'Name', 'Description'),
      'Discussion'=> array('DiscussionID', 'Name', 'CategoryID', 'DateCreated', 'AuthUserID', 'DateLastActive', 'Closed', 'Sticky', 'CountComments', 'Sink', 'LastUserID'),
      'Comment'=> array('CommentID', 'DiscussionID', 'AuthUserID', 'DateCreated', 'EditUserID', 'DateEdited', 'Body')
      );
   
   /**
    * Forum-specific export format
    * @todo Project file size / export time and possibly break into multiple files
    * @param ExportModel $Ex
    * 
    */
   protected function ForumExport($Ex) {
      $this->Ex = $Ex;

      // Get the characterset for the comments.
      $CharacterSet = $Ex->GetCharacterSet('Comment');
      if ($CharacterSet)
         $Ex->CharacterSet = $CharacterSet;

      // Begin
      $Ex->BeginExport('', 'Vanilla 1.*');
      
      // Users
      $User_Map = array(
         'UserID'=>'UserID',
         'Name'=>'Name',
         'Password'=>'Password',
         'Email'=>'Email',
         'Icon'=>'Photo',
         'CountComments'=>'CountComments',
         'Discovery'=>'DiscoveryText'
      );   
      $Ex->ExportTable('User', "SELECT * FROM :_User", $User_Map);  // ":_" will be replaced by database prefix
      
      // Roles
      
      // Since the zero role is a valid role in Vanilla 1 then we'll have to reassign it.
      $R = $Ex->Query('select max(RoleID) as RoleID from :_Role');
      $ZeroRoleID = 0;
      if (is_resource($R)) {
         while (($Row = @mysql_fetch_assoc($R)) !== false) {
            $ZeroRoleID = $Row['RoleID'];
         }
      }
      $ZeroRoleID++;

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
      $Ex->ExportTable('Role', "select RoleID, Name, Description from :_Role union all select $ZeroRoleID, 'Applicant', 'Created by the Vanilla Porter'", $Role_Map);

      $Permission_Map = array(
         'RoleID' => 'RoleID',
         'PERMISSION_SIGN_IN' => 'Garden.SignIn.Allow',
         'Permissions' => array('Column' => 'Vanilla.Comments.Add', 'Type' => 'tinyint', 'Filter' => array($this, 'FilterPermissions')),
         'PERMISSION_START_DISCUSSION' => array('Column' => 'Vanilla.Discussions.Add', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_SINK_DISCUSSION' => array('Column' => 'Vanilla.Discussions.Sink', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_STICK_DISCUSSIONS' => array('Column' => 'Vanilla.Discussions.Announce', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_CLOSE_DISCUSSIONS' => array('Column' => 'Vanilla.Discussions.Close', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_EDIT_DISCUSSIONS' => array('Column' => 'Vanilla.Discussions.Edit', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_EDIT_COMMENTS' => array('Column' => 'Vanilla.Comments.Edit', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_ADD_CATEGORIES' => array('Column' => 'Vanilla.Categories.Manage', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_APPROVE_APPLICANTS' => array('Column' => 'Garden.Applicants.Manage', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_EDIT_USERS' => array('Column' => 'Garden.Users.Edit', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_MANAGE_REGISTRATION' => array('Column' => 'Garden.Registration.Manage', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_CHANGE_APPLICATION_SETTINGS' => array('Column' => 'Garden.Settings.Manage', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_MANAGE_EXTENSIONS' => array('Column' => 'Garden.Plugins.Manage', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool')),
         'PERMISSION_MANAGE_THEMES' => array('Column' => 'Garden.Themes.Manage', 'Type' => 'tinyint', 'Filter' => array($this, 'ForceBool'))
      );
      $Ex->ExportTable('Permission', "select * from :_Role", $Permission_Map);

      // UserRoles
      /*
		    'UserID' => 'int', 
		    'RoleID' => 'int'
		 */
      $UserRole_Map = array(
         'UserID' => 'UserID', 
         'RoleID'=> 'RoleID'
      );
      $Ex->ExportTable('UserRole', "select UserID, case RoleID when 0 then $ZeroRoleID else RoleID end as RoleID from :_User", $UserRole_Map);
      
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
         'DateCreated'=>'DateInserted',
         'DateCreated2'=>'DateUpdated',
         'AuthUserID'=>'InsertUserID',
         'DateLastActive'=>'DateLastComment',
         'AuthUserID2'=>'UpdateUserID',
         'Closed'=>'Closed',
         'Sticky'=>'Announce',
         'CountComments'=>'CountComments',
         'Sink'=>'Sink',
         'LastUserID'=>'LastCommentUserID'
      );
      $Ex->ExportTable('Discussion',
         "SELECT d.*,
            d.LastUserID as LastCommentUserID,
            d.DateCreated as DateCreated2, d.AuthUserID as AuthUserID2
         FROM :_Discussion d
         WHERE coalesce(d.WhisperUserID, 0) = 0 and d.Active = 1", $Discussion_Map);
      
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
         'Body' => 'Body',
         'FormatType' => 'Format'
      );
      $Ex->ExportTable('Comment', "
         SELECT 
            c.*
         FROM :_Comment c
         JOIN :_Discussion d
            ON c.DiscussionID = d.DiscussionID
         WHERE coalesce(d.WhisperUserID, 0) = 0
            AND coalesce(c.WhisperUserID, 0) = 0", $Comment_Map);

      $Ex->ExportTable('UserDiscussion', "
         SELECT
            w.UserID,
            w.DiscussionID,
            w.CountComments,
            w.LastViewed as DateLastViewed,
            case when b.UserID is not null then 1 else 0 end AS Bookmarked
         FROM :_UserDiscussionWatch w
         LEFT JOIN :_UserBookmark b
            ON w.DiscussionID = b.DiscussionID AND w.UserID = b.UserID");
      
      // Conversations

      // Create a mapping table for conversations.
      // This cannot be a temporary table because of some of the union selects it is used in below.
      $Ex->Query("create table :_V1Conversation (ConversationID int auto_increment primary key, DiscussionID int, UserID1 int, UserID2 int, DateCreated datetime, EditUserID int, DateEdited datetime)");

      $Ex->Query("insert :_V1Conversation (DiscussionID, UserID1, UserID2, DateCreated, EditUserID, DateEdited)
         select
           DiscussionID,
           AuthUserID as UserID1,
           WhisperUserID as UserID2,
           min(DateCreated),
           max(EditUserID),
           max(DateEdited)
         from :_Comment
         where coalesce(WhisperUserID, 0) <> 0
         group by DiscussionID, AuthUserID, WhisperUserID

         union

         select
           DiscussionID,
           AuthUserID as UserID1,
           WhisperUserID as UserID2,
           DateCreated,
           WhisperFromLastUserID,
           DateLastWhisper
         from :_Discussion
         where coalesce(WhisperUserID, 0) <> 0");

      // Delete redundant conversations.
      $Ex->Query("create index ix_V1UserID1 on :_V1Conversation (DiscussionID, UserID1)"); // for speed
      $Ex->Query("delete t.*
         from :_V1Conversation t
         inner join :_Comment c
           on c.DiscussionID = t.DiscussionID
             and c.AuthUserID = t.UserID2
             and c.WhisperUserID = t.UserID1
             and c.AuthUserID < c.WhisperUserID");


      $Conversation_Map = array(
         'UserID1' => 'InsertUserID',
         'DateCreated' => 'DateInserted',
         'EditUserID' => 'UpdateUserID',
         'DateEdited' => 'DateUpdated'
      );
      $Ex->ExportTable('Conversation', "select * from :_V1Conversation", $Conversation_Map);
      
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
         select c.CommentID, t.ConversationID, c.AuthUserID, c.DateCreated, c.Body
         from :_Comment c
         join :_V1Conversation t
           on t.DiscussionID = c.DiscussionID
             and c.WhisperUserID in (t.UserID1, t.UserID2)
             and c.AuthUserID in (t.UserID1, t.UserID2)
         where c.WhisperUserID > 0

         union

         select c.CommentID, t.ConversationID, c.AuthUserID, c.DateCreated, c.Body
         from :_Comment c
         join :_Discussion d
          on c.DiscussionID = d.DiscussionID
         join :_V1Conversation t
           on t.DiscussionID = d.DiscussionID
             and d.WhisperUserID in (t.UserID1, t.UserID2)
             and d.AuthUserID in (t.UserID1, t.UserID2)
         where d.WhisperUserID > 0", $ConversationMessage_Map);
      
      // UserConversation
      /*
         'UserID' => 'int', 
         'ConversationID' => 'int', 
         'LastMessageID' => 'int'
      */
      $UserConversation_Map = array(
         'UserID' => 'UserID',
         'ConversationID' => 'ConversationID'
      );
      $Ex->ExportTable('UserConversation', 
         "select UserID1 as UserID, ConversationID
         from :_V1Conversation

         union

         select UserID2 as UserID, ConversationID
         from :_V1Conversation", $UserConversation_Map);

      $Ex->Query("drop table :_V1Conversation");

      // Media
      if ($Ex->Exists('Attachment')) {
         $Media_Map = array(
            'AttachmentID' => 'MediaID',
            'Name' => 'Name',
            'MimeType' => 'Type',
            'Size' => 'Size',
            //'StorageMethod',
            'Path' => array('Column' => 'Path', 'Filter' => array($this, 'StripMediaPath')),
            'UserID' => 'InsertUserID',
            'DateCreated' => 'DateInserted',
            'CommentID' => 'ForeignID'
            //'ForeignTable'
          );
         $Ex->ExportTable('Media',
            "select a.*, 'local' as StorageMethod, 'comment' as ForeignTable from :_Attachment a",
            $Media_Map);
      }
         
      // End
      $Ex->EndExport();
   }

   function StripMediaPath($AbsPath) {
      if (($Pos = strpos($AbsPath, '/uploads/')) !== FALSE)
         return substr($AbsPath, $Pos + 9);
      return $AbsPath;
   }

   function FilterPermissions($Permissions, $ColumnName, &$Row) {
      $Permissions2 = unserialize($Permissions);

      foreach ($Permissions2 as $Name => $Value) {
         if (is_null($Value))
            $Permissions2[$Name] = FALSE;
      }

      if (is_array($Permissions2)) {
         $Row = array_merge($Row, $Permissions2);
         $this->Ex->CurrentRow = $Row;
         return isset($Permissions2['PERMISSION_ADD_COMMENTS']) ? $Permissions2['PERMISSION_ADD_COMMENTS'] : FALSE;
      }
      return FALSE;
   }

   function ForceBool($Value) {
      if ($Value)
         return TRUE;
      return FALSE;
   }
}
?>
