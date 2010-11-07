<?php
/**
 * ppPress exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

class SimplePress extends ExportController {

   /** @var array Required tables => columns */
   protected $SourceTables = array(
      'sfforums' => array(),
      'sfposts' => array(),
      'sftopics' => array(),
      'users' => array('ID', 'user_nicename', 'user_pass', 'user_email', 'user_registered')
      //'meta' => array()
   );

   /**
    * Forum-specific export format.
    * @param ExportModel $Ex
    */
   protected function ForumExport($Ex) {
      // Begin
      $Ex->BeginExport('', 'SimplePress 1.*', array('HashMethod' => 'Vanilla'));

      // Users
      $User_Map = array(
         'user_id'=>'UserID',
         'display_name'=>'Name',
         'user_pass'=>'Password',
         'user_email'=>'Email',
         'user_registered'=>'DateInserted'
      );
      $Ex->ExportTable('User', 
         "select m.*, u.user_pass, u.user_email
          from :_users u
          join :_sfmembers m
            on u.ID = m.user_id", $User_Map);  // ":_" will be replace by database prefix

      // Roles
      $Role_Map = array(
          'usergroup_id' => 'RoleID',
          'usergroup_name' => 'Name',
          'usergroup_desc' => 'Description'
      );
      $Ex->ExportTable('Role',
         "select * from :_sfusergroups", $Role_Map);

      // UserRoles
      $UserRole_Map = array(
         'user_id'=>'UserID',
         'usergroup_id'=>'RoleID'
      );
      $Ex->ExportTable('UserRole',
         "select * from :_sfmemberships", $UserRole_Map);

      // Categories
      $Category_Map = array(
         'forum_id'=>'CategoryID',
         'forum_name'=>'Name',
         'forum_desc'=>'Description',
         'form_slug'=>'UrlCode'
      );
      $Ex->ExportTable('Category', "select *,
         nullif(parent,0) as ParentCategoryID
         from :_sfforums", $Category_Map);

      // Discussions
      $Discussion_Map = array(
         'topic_id'=>'DiscussionID',
         'forum_id'=>'CategoryID',
         'user_id'=>'InsertUserID',
         'topic_name'=>'Name',
			'Format'=>'Format',
         'topic_date'=>'DateInserted',
         'topic_pinned'=>'Announce'
      );
      $Ex->ExportTable('Discussion', "select t.*,
				'Html' as Format
         from :_sftopics t", $Discussion_Map);

      // Comments
      $Comment_Map = array(
         'post_id' => 'CommentID',
         'topic_id' => 'DiscussionID',
         'post_content' => 'Body',
			'Format' => 'Format',
         'user_id' => 'InsertUserID',
         'post_date' => 'DateInserted'
      );
      $Ex->ExportTable('Comment', "select p.*,
				'Html' as Format
         from :_sfposts p", $Comment_Map);

      // Conversation.
      $Conv_Map = array(
         'message_id' => 'ConversationID',
         'from_id' => 'InsertUserID',
         'sent_date' => 'DateInserted'
      );
      $Ex->ExportTable('Conversation',
         "select *
         from :_sfmessages
         where is_reply = 0", $Conv_Map);

      // ConversationMessage.
      $ConvMessage_Map = array(
         'message_id' => 'MessageID',
         'from_id' => 'InsertUserID',
         'message' => array('Column'=>'Body')
      );
      $Ex->ExportTable('ConversationMessage',
         'select c.message_id as ConversationID, m.*
         from :_sfmessages c
         join :_sfmessages m
           on (m.is_reply = 0 and m.message_id = c.message_id) or (m.is_reply = 1 and c.is_reply = 0 and m.message_slug = c.message_slug and m.from_id in (c.from_id, c.to_id) and m.to_id in (c.from_id, c.to_id));',
         $ConvMessage_Map);

      // UserConversation
      $UserConv_Map = array(
         'message_id' => 'ConversationID',
         'from_id' => 'UserID'
      );
      $Ex->ExportTable('UserConversation',
         'select message_id, from_id
         from :_sfmessages
         where is_reply = 0

         union

         select message_id, to_id
         from :_sfmessages
         where is_reply = 0',
         $UserConv_Map);

      // End
      $Ex->EndExport();
   }
}
?>