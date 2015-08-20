<?php
/**
 * esotalk exporter tool.
 *
 * @copyright Vanilla Forums Inc. 2010-2014
 * @license GNU GPL2
 * @package VanillaPorter
 * @see functions.commandline.php for command line usage.
 */

$Supported['esotalk'] = array('name' => 'esoTalk', 'prefix' => 'et_');
$Supported['esotalk']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Bookmarks' => 1,
    'Passwords' => 1,
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
            'confirmed' => 'Verified',
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
            'conversationId' => 'DiscussionID',
            'title' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'channelId' => 'CategoryID',
            'memberId' => 'InsertUserID',
            'sticky' => 'Announce',
            'locked' => 'Closed',
            //'countPosts' => 'CountComments',
            'lastPostMemberId' => 'LastCommentUserID',
            'content' => 'Body',
        );
        // The body of the OP is in the post table.
        $Ex->ExportTable('Discussion', "
			select
				c.conversationId,
				c.title,
				c.channelId,
				p.memberId,
				c.sticky,
				c.locked,
				c.lastPostMemberId,
				p.content,
				'BBCode' as Format,
				from_unixtime(startTime) as DateInserted,
				from_unixtime(lastPostTime) as DateLastComment
			from :_conversation c
			left join :_post p
				on p.conversationId = c.conversationId
			where private = 0
			group by c.conversationId
			group by p.time", $Discussion_Map);


        // Comment.
        $Comment_Map = array(
            'postId' => 'CommentID',
            'conversationId' => 'DiscussionID',
            'content' => 'Body',
            'memberId' => 'InsertUserID',
            'editMemberId' => 'UpdateUserID',
        );
        // Now we need to omit the comments we used as the OP.
        $Ex->ExportTable('Comment', "
		select p.*,
				'BBCode' as Format,
				from_unixtime(time) as DateInserted,
				from_unixtime(editTime) as DateUpdated
		from :_post p
		inner join :_conversation c ON c.conversationId = p.conversationId
		and c.private = 0
		join
			( select conversationId,
				min(postId) as m
			from :_post
			group by conversationId) r on r.conversationId = c.conversationId
		where p.postId<>r.m", $Comment_Map);


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
		$Conversation_map = array(
            'conversationId' => 'ConversationID',
            'countPosts' => 'CountMessages',
            'startMemberId' => 'InsertUserID',
            'countPosts' => 'CountMessages',
        );

        $Ex->ExportTable('Conversation', "
                select p.*,
                'BBCode' as Format,
                from_unixtime(time) as DateInserted,
                from_unixtime(lastposttime) as DateUpdated
        from :_post p
        inner join :_conversation c on c.conversationId = p.conversationId
        and c.private = 1", $Conversation_map);

        $UserConversation_map = array(
            'conversationId' => 'ConversationID',
            'memberId' => 'UserID',

        );

        $Ex->ExportTable('UserConversation', "
        select distinct a.fromMemberId AS memberId, a.type, c.private, c.conversationId from :_activity a
        inner join :_conversation c on c.conversationId = a.conversationId
        and c.private = 1 and a.type = 'privateAdd'
        union all
        select distinct a.memberId as memberId, a.type, c.private, c.conversationId from :_activity a
        inner join :_conversation c on c.conversationId = a.conversationId
        and c.private = 1 and a.type = 'privateAdd'", $UserConversation_map);

        $UserConversationMessage_map = array(
            'postId' => 'MessageID',
            'conversationId' => 'ConversationID',
            'content' => 'Body',
            'memberId' => 'InsertUserID',

        );

        $Ex->ExportTable('ConversationMessage', "
                select p.*,
                'BBCode' as Format,
                from_unixtime(time) as DateInserted
        from :_post p
        inner join :_conversation c on c.conversationId = p.conversationId and c.private = 1", $UserConversationMessage_map);

        $Ex->EndExport();
    }
}

?>
