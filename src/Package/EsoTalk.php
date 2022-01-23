<?php

/**
 * esotalk exporter tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 * @author  Frederik Nielsen
 */

namespace Porter\Package;

use Porter\ExportController;
use Porter\ExportModel;

class EsoTalk extends ExportController
{
    public const SUPPORTED = [
        'name' => 'esoTalk',
        'prefix' => 'et_',
        'options' => [
        ],
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 0,
            'PrivateMessages' => 1,
            'Signatures' => 0,
            'Attachments' => 0,
            'Bookmarks' => 1,
            'Permissions' => 0,
            'Badges' => 0,
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 0,
            'Reactions' => 0,
            'Articles' => 0,
        ]
    ];

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see   $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex)
    {
        $characterSet = $ex->getCharacterSet('post');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->beginExport('', 'esotalk');

        $this->users($ex);

        $this->roles($ex);

        $this->categories($ex);

        $this->discussions($ex);

        $this->comments($ex);

        $this->bookmarks($ex);

        // Permission.
        // :_channel_group

        // Media.
        // :_attachment

        $this->conversations($ex);

        $ex->endExport();
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $user_Map = array(
            'memberId' => 'UserID',
            'username' => 'Name',
            'email' => 'Email',
            'confirmed' => 'Verified',
            'password' => 'Password',
        );
        $ex->exportTable(
            'User',
            "
         select u.*, 'crypt' as HashMethod,
            FROM_UNIXTIME(joinTime) as DateInserted,
            FROM_UNIXTIME(lastActionTime) as DateLastActive,
            if(account='suspended',1,0) as Banned
         from :_member u",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $role_Map = array(
            'groupId' => 'RoleID',
            'name' => 'Name',
        );
        $ex->exportTable(
            'Role',
            "
         select groupId, name
         from :_group
         union select max(groupId)+1, 'Member' from :_group
         union select max(groupId)+2, 'Administrator' from :_group
         ",
            $role_Map
        );


        // User Role.
        $userRole_Map = array(
            'memberId' => 'UserID',
            'groupId' => 'RoleID',
        );
        // Create fake 'member' and 'administrator' roles to account for them being set separately on member table.
        $ex->exportTable(
            'UserRole',
            "
         select u.memberId, u.groupId
         from :_member_group u
         union all
         select memberId, (select max(groupId)+1 from :_group) from :_member where account='member'
         union all
         select memberId, (select max(groupId)+2 from :_group) from :_member where account='administrator'
         ",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array(
            'channelId' => 'CategoryID',
            'title' => 'Name',
            'slug' => 'UrlCode',
            'description' => 'Description',
            'parentId' => 'ParentCategoryID',
            'countConversations' => 'CountDiscussions',
            //'countPosts' => 'CountComments',
        );
        $ex->exportTable(
            'Category',
            "
         select *
         from :_channel c",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
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
        $ex->exportTable(
            'Discussion',
            "
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
			order by p.time",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $comment_Map = array(
            'postId' => 'CommentID',
            'conversationId' => 'DiscussionID',
            'content' => 'Body',
            'memberId' => 'InsertUserID',
            'editMemberId' => 'UpdateUserID',
        );
        // Now we need to omit the comments we used as the OP.
        $ex->exportTable(
            'Comment',
            "
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
		where p.postId<>r.m",
            $comment_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function bookmarks(ExportModel $ex): void
    {
        $userDiscussion_Map = array(
            'id' => 'UserID',
            'conversationId' => 'DiscussionID',
        );
        $ex->exportTable(
            'UserDiscussion',
            "
         select *
         from :_member_conversation
         where starred = 1",
            $userDiscussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function conversations(ExportModel $ex): void
    {
        $conversation_map = array(
            'conversationId' => 'ConversationID',
            'countPosts' => 'CountMessages',
            'startMemberId' => 'InsertUserID',
        );
        $ex->exportTable(
            'Conversation',
            "
                select p.*,
                'BBCode' as Format,
                from_unixtime(time) as DateInserted,
                from_unixtime(lastposttime) as DateUpdated
        from :_post p
        inner join :_conversation c on c.conversationId = p.conversationId
        and c.private = 1",
            $conversation_map
        );

        $userConversation_map = array(
            'conversationId' => 'ConversationID',
            'memberId' => 'UserID',

        );
        $ex->exportTable(
            'UserConversation',
            "
        select distinct a.fromMemberId as memberId, a.type, c.private, c.conversationId from :_activity a
        inner join :_conversation c on c.conversationId = a.conversationId
        and c.private = 1 and a.type = 'privateAdd'
        union all
        select distinct a.memberId as memberId, a.type, c.private, c.conversationId from :_activity a
        inner join :_conversation c on c.conversationId = a.conversationId
        and c.private = 1 and a.type = 'privateAdd'",
            $userConversation_map
        );

        $userConversationMessage_map = array(
            'postId' => 'MessageID',
            'conversationId' => 'ConversationID',
            'content' => 'Body',
            'memberId' => 'InsertUserID',

        );
        $ex->exportTable(
            'ConversationMessage',
            "
                select p.*,
                'BBCode' as Format,
                from_unixtime(time) as DateInserted
        from :_post p
        inner join :_conversation c on c.conversationId = p.conversationId and c.private = 1",
            $userConversationMessage_map
        );
    }
}
