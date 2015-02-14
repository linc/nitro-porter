<?php
/**
 * esotalk exporter tool.
 *
 * @copyright Vanilla Forums Inc. 2010-2014
 * @license GNU GPL2
 * @package VanillaPorter
 * @see functions.commandline.php for command line usage.
 */

$Supported['esotalk'] = array('name'=> 'esoTalk', 'prefix'=>'forum_');
$Supported['esotalk']['features'] = array(
   'Comments'        => 1,
   'Discussions'     => 1,
   'Users'           => 1,
   'Categories'      => 1,
   'Roles'           => 1,
   'Bookmarks'       => 1,
   'Passwords'       => 1,
);

class esotalk extends ExportController {
   /**
    * Main export process.
    *
    * @param ExportModel $Ex
    * @see $_Structures in ExportModel for allowed destination tables & columns.
    */
   public function ForumExport($Ex) {
      // Get the characterset for the comments.
      // Usually the comments table is the best target for this.
      $CharacterSet = $Ex->GetCharacterSet(':_post');
      if ($CharacterSet) {
         $Ex->CharacterSet = $CharacterSet;
      }

      // Reiterate the platform name here to be included in the porter file header.
      $Ex->BeginExport('', 'esotalk');


      // User.
      $User_Map = array(
         'memberId' => 'UserID',
         'username' => 'Name',
         'email' => 'Email',
         'confirmed' => 'Confirmed',
         'password' => 'Password',
      );
      $Ex->ExportTable('User', "
         select u.*, 'crypt' as HashMethod,
            FROM_UNIXTIME(joinTime) as DateInserted,
            FROM_UNIXTIME(lastActionTime) as DateLastActive,
            if(account='suspended',1,0) as Banned
         from :_member u", $User_Map);


      // Role.
      $Role_Map = array(
         'groupId' => 'RoleID',
         'name' => 'Name',
      );
      $Ex->ExportTable('Role', "
         select groupId, name
         from :_group
         union select max(groupId)+1, 'Member' from :_group
         union select max(groupId)+2, 'Administrator' from :_group
         ", $Role_Map);


      // User Role.
      $UserRole_Map = array(
         'memberId' => 'UserID',
         'groupId' => 'RoleID',
      );
      // Create fake 'member' and 'administrator' roles to account for them being set separately on member table.
      $Ex->ExportTable('UserRole', "
         select u.memberId, u.groupId
         from :_member_group u
         union all
         select memberId, (select max(groupId)+1 from :_group) from :_member where account='member'
         union all
         select memberId, (select max(groupId)+2 from :_group) from :_member where account='administrator'
         ", $UserRole_Map);

      
      // Category.
      $Category_Map = array(
         'channelId' => 'CategoryID',
         'title' => 'Name',
         'slug' => 'UrlCode',
         'description' => 'Description',
         'parentId' => 'ParentCategoryID',
         'countConversations' => 'CountDiscussions',
         //'countPosts' => 'CountComments',
      );
      $Ex->ExportTable('Category', "
         select *
         from :_channel c", $Category_Map);


      // Discussion.
      $Discussion_Map = array(
         'conversationID' => 'DiscussionID',
         'title' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
         'channelId' => 'CategoryID',
         'memberId' => 'InsertUserID',
         'sticky' => 'Announce',
         'locked' => 'Closed',
         //'countPosts' => 'CountComments',
         'lastPostMemberId' => 'LastCommentUserID',
         'content' => 'Body',
      );
      $Ex->ExportTable('Discussion', "
         select *, 'BBCode' as Format,
            FROM_UNIXTIME(startTime) as DateInserted,
            FROM_UNIXTIME(lastPostTime) as DateLastComment
         from :_conversation c
         left join :_post p on p.conversationId = c.conversationId
         where private = 0
         group by c.conversationId
         order by p.time", $Discussion_Map);


      // Comment.
      $Comment_Map = array(
         'postId' => 'CommentID',
         'conversationId' => 'DiscussionID',
         'content' => 'Body',
         'memberId' => 'InsertUserID',
         'editMemberId' => 'UpdateUserID',
      );
      $Ex->ExportTable('Comment', "
         select p.*, 'BBCode' as Format,
            FROM_UNIXTIME(time) as DateInserted,
            FROM_UNIXTIME(editTime) as DateUpdated
         from :_post p
         left join :_conversation c on c.conversationId = p.conversationId
         where c.private = 0", $Comment_Map);


      // UserDiscussion.
      $UserDiscussion_Map = array(
         'id' => 'UserID',
         'conversationId' => 'DiscussionID',
      );
      $Ex->ExportTable('UserDiscussion', "
         select *
         from :_member_conversation
         where starred = 1", $UserDiscussion_Map);


      // Permission.
      // :_channel_group


      // Media.
      // :_attachment


      // Conversation.
      // :_conversation where private = 1


      
      $Ex->EndExport();
   }
}
?>