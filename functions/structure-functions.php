<?php
/**
 * @copyright Vanilla Forums Inc. 2010-2015
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Define acceptable data fields to sent to Vanilla.
 *
 * Format is array of Table => array(Column -> Type).
 *
 * @return array
 */
function VanillaStructure() {
    // Adding new items without matching existing spacing costs 2 toes.
    return array(
        'Activity' => array(
            'ActivityID' => 'int',
            'ActivityTypeID' => 'int',
            'NotifyUserID' => 'int',
            'ActivityUserID' => 'int',
            'RegardingUserID' => 'int',
            'Photo' => 'varchar(255)',
            'HeadlineFormat' => 'varchar(255)',
            'Story' => 'text',
            'Format' => 'varchar(10)',
            'Route' => 'varchar(255)',
            'RecordType' => 'varchar(20)',
            'RecordID' => 'int',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'InsertIPAddress' => 'varchar(15)',
            'DateUpdated' => 'datetime',
            'Notified' => 'tinyint',
            'Emailed' => 'tinyint',
            'Data' => 'text'
        ),
        'ActivityComment' => array(
            'ActivityCommentID' => 'int',
            'ActivityID' => 'int',
            'Body' => 'text',
            'Format' => 'varchar(20)',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'InsertIPAddress' => 'varchar(15)'
        ),
        'ActivityType' => array(
            'ActivityTypeID' => 'int',
            'Name' => 'varchar(20)',
            'AllowComments' => 'tinyint',
            'ShowIcon' => 'tinyint',
            'ProfileHeadline' => 'varchar(255)',
            'FullHeadline' => 'varchar(255)',
            'RouteCode' => 'varchar(255)',
            'Notify' => 'tinyint',
            'Public' => 'tinyint'
        ),
        'AnalyticsLocal' => array(
            'TimeSlot' => 'varchar(8)',
            'Views' => 'int',
            'EmbedViews' => 'int'
        ),
        'Attachment' => array(
            'AttachmentID' => 'int',
            'Type' => 'varchar(64)',
            'ForeignID' => 'varchar(50)',
            'ForeignUserID' => 'int',
            'Source' => 'varchar(64)',
            'SourceID' => 'varchar(32)',
            'SourceURL' => 'varchar(255)',
            'Attributes' => 'text',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'InsertIPAddress' => 'varchar(64)',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'UpdateIPAddress' => 'varchar(15)'
        ),
        'Ban' => array(
            'BanID' => 'int',
            //'BanType' => array('IPAddress','Name','Email'),
            'BanValue' => 'varchar(50)',
            'Notes' => 'varchar(255)',
            'CountUsers' => 'int',
            'CountBlockedRegistrations' => 'int',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'InsertIPAddress' => 'varchar(15)',
            'UpdateUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateIPAddress' => 'varchar(15)'
        ),
        'Category' => array(
            'CategoryID' => 'int',
            'ParentCategoryID' => 'int',
            'TreeLeft' => 'int',
            'TreeRight' => 'int',
            'Depth' => 'int',
            'CountDiscussions' => 'int',
            'CountComments' => 'int',
            'DateMarkedRead' => 'datetime',
            'AllowDiscussions' => 'tinyint',
            'Archived' => 'tinyint',
            'Name' => 'varchar(255)',
            'UrlCode' => 'varchar(255)',
            'Description' => 'varchar(500)',
            'Sort' => 'int',
            'CssClass' => 'varchar(50)',
            'Photo' => 'varchar(255)',
            'PermissionCategoryID' => 'int',
            'PointsCategoryID' => 'int',
            'HideAllDiscussions' => 'tinyint',
            //'DisplayAs' => array('Categories','Discussions','Heading','Default'),
            'InsertUserID' => 'int',
            'UpdateUserID' => 'int',
            'DateInserted' => 'datetime',
            'DateUpdated' => 'datetime',
            'LastCommentID' => 'int',
            'LastDiscussionID' => 'int',
            'LastDateInserted' => 'datetime',
            'AllowedDiscussionTypes' => 'varchar(255)',
            'DefaultDiscussionType' => 'varchar(10)',
            'AllowGroups' => 'tinyint'
        ),
        'Comment' => array(
            'CommentID' => 'int',
            'DiscussionID' => 'int',
            'InsertUserID' => 'int',
            'UpdateUserID' => 'int',
            'DeleteUserID' => 'int',
            'Body' => 'text',
            'Format' => 'varchar(20)',
            'DateInserted' => 'datetime',
            'DateDeleted' => 'datetime',
            'DateUpdated' => 'datetime',
            'InsertIPAddress' => 'varchar(15)',
            'UpdateIPAddress' => 'varchar(15)',
            'Flag' => 'tinyint',
            'Score' => 'float',
            'Attributes' => 'text'
        ),
        'Conversation' => array(
            'ConversationID' => 'int',
            'Type' => 'varchar(10)',
            'ForeignID' => 'varchar(40)',
            'Subject' => 'varchar(100)',
            'Contributors' => 'varchar(255)',
            'FirstMessageID' => 'int',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'InsertIPAddress' => 'varchar(15)',
            'UpdateUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateIPAddress' => 'varchar(15)',
            'CountMessages' => 'int',
            'CountParticipants' => 'int',
            'LastMessageID' => 'int',
            'RegardingID' => 'int'
        ),
        'ConversationMessage' => array(
            'MessageID' => 'int',
            'ConversationID' => 'int',
            'Body' => 'text',
            'Format' => 'varchar(20)',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'InsertIPAddress' => 'varchar(15)'
        ),
        'Discussion' => array(
            'DiscussionID' => 'int',
            'Type' => 'varchar(10)',
            'ForeignID' => 'varchar(32)',
            'CategoryID' => 'int',
            'InsertUserID' => 'int',
            'UpdateUserID' => 'int',
            'FirstCommentID' => 'int',
            'LastCommentID' => 'int',
            'Name' => 'varchar(100)',
            'Body' => 'text',
            'Format' => 'varchar(20)',
            'Tags' => 'text',
            'CountComments' => 'int',
            'CountBookmarks' => 'int',
            'CountViews' => 'int',
            'Closed' => 'tinyint',
            'Announce' => 'tinyint',
            'Sink' => 'tinyint',
            'DateInserted' => 'datetime',
            'DateUpdated' => 'datetime',
            'InsertIPAddress' => 'varchar(15)',
            'UpdateIPAddress' => 'varchar(15)',
            'DateLastComment' => 'datetime',
            'LastCommentUserID' => 'int',
            'Score' => 'float',
            'Attributes' => 'text',
            'RegardingID' => 'int',
            'GroupID' => 'int'
        ),
        'Draft' => array(
            'DraftID' => 'int',
            'DiscussionID' => 'int',
            'CategoryID' => 'int',
            'InsertUserID' => 'int',
            'UpdateUserID' => 'int',
            'Name' => 'varchar(100)',
            'Tags' => 'varchar(255)',
            'Closed' => 'tinyint',
            'Announce' => 'tinyint',
            'Sink' => 'tinyint',
            'Body' => 'text',
            'Format' => 'varchar(20)',
            'DateInserted' => 'datetime',
            'DateUpdated' => 'datetime'
        ),
        'Event' => array(
            'EventID' => 'int',
            'Name' => 'varchar(255)',
            'Body' => 'text',
            'Format' => 'varchar(10)',
            'DateStarts' => 'datetime',
            'DateEnds' => 'datetime',
            'Timezone' => 'varchar(64)',
            'AllDayEvent' => 'tinyint',
            'Location' => 'varchar(255)',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'GroupID' => 'int'
        ),
        'Group' => array(
            'GroupID' => 'int',
            'Name' => 'varchar(255)',
            'Description' => 'text',
            'Format' => 'varchar(10)',
            'CategoryID' => 'int',
            'Icon' => 'varchar(255)',
            'Banner' => 'varchar(255)',
            'Privacy' => 'varchar(255)', // 'Public', 'Private'
            'Registration' => 'varchar(255)', // 'Public', 'Approval', 'Invite'
            'Visibility' => 'varchar(255)', // 'Public', 'Members'
            'CountMembers' => 'int',
            'CountDiscussions' => 'int',
            'DateLastComment' => 'datetime',
            'LastCommentID' => 'int',
            'LastDiscussionID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'Attributes' => 'text'
        ),
        'GroupApplicant' => array(
            'GroupApplicantID' => 'int',
            'GroupID' => 'int',
            'UserID' => 'int',
            'Type' => 'varchar(255)', // 'Application', 'Invitation', 'Denied', 'Banned'
            'Reason' => 'varchar(200)',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int'
        ),
        'Invitation' => array(
            'InvitationID' => 'int',
            'Email' => 'varchar(200)',
            'Name' => 'varchar(50)',
            'RoleIDs' => 'text',
            'Code' => 'varchar(50)',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'AcceptedUserID' => 'int',
            'DateExpires' => 'datetime'
        ),
        'Log' => array(
            'LogID' => 'int',
            //'Operation' => array('Delete','Edit','Spam','Moderate','Pending','Ban','Error'),
            //'RecordType' => array('Discussion','Comment','User','Registration','Activity','ActivityComment','Configuration','Group'),
            'TransactionLogID' => 'int',
            'RecordID' => 'int',
            'RecordUserID' => 'int',
            'RecordDate' => 'datetime',
            'RecordIPAddress' => 'varchar(15)',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'InsertIPAddress' => 'varchar(15)',
            'OtherUserIDs' => 'varchar(255)',
            'DateUpdated' => 'datetime',
            'ParentRecordID' => 'int',
            'CategoryID' => 'int',
            'Data' => 'mediumtext',
            'CountGroup' => 'int'
        ),
        'Media' => array(
            'MediaID' => 'int',
            'Name' => 'varchar(255)',
            'Path' => 'varchar(255)',
            'Type' => 'varchar(128)',
            'Size' => 'int',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'ForeignID' => 'int',
            'ForeignTable' => 'varchar(24)',
            'ImageWidth' => 'smallint',
            'ImageHeight' => 'smallint',
            'ThumbWidth' => 'smallint',
            'ThumbHeight' => 'smallint',
            'ThumbPath' => 'varchar(255)'
        ),
        'Message' => array(
            'MessageID' => 'int',
            'Content' => 'text',
            'Format' => 'varchar(20)',
            'AllowDismiss' => 'tinyint',
            'Enabled' => 'tinyint',
            'Application' => 'varchar(255)',
            'Controller' => 'varchar(255)',
            'Method' => 'varchar(255)',
            'CategoryID' => 'int',
            'IncludeSubcategories' => 'tinyint',
            'AssetTarget' => 'varchar(20)',
            'CssClass' => 'varchar(20)',
            'Sort' => 'int'
        ),
        'Permission' => array(
            'PermissionID' => 'int',
            'RoleID' => 'int',
            'JunctionTable' => 'varchar(100)',
            'JunctionColumn' => 'varchar(100)',
            'JunctionID' => 'int',
            '_Permissions' => 'varchar(255)',
            'Garden.Email.View' => 'tinyint',
            'Garden.Settings.Manage' => 'tinyint',
            'Garden.Settings.View' => 'tinyint',
            'Garden.SignIn.Allow' => 'tinyint',
            'Garden.Users.Add' => 'tinyint',
            'Garden.Users.Edit' => 'tinyint',
            'Garden.Users.Delete' => 'tinyint',
            'Garden.Users.Approve' => 'tinyint',
            'Garden.Activity.Delete' => 'tinyint',
            'Garden.Activity.View' => 'tinyint',
            'Garden.Profiles.View' => 'tinyint',
            'Garden.Profiles.Edit' => 'tinyint',
            'Garden.Curation.Manage' => 'tinyint',
            'Garden.Moderation.Manage' => 'tinyint',
            'Garden.PersonalInfo.View' => 'tinyint',
            'Garden.AdvancedNotifications.Allow' => 'tinyint',
            'Garden.Community.Manage' => 'tinyint',
            'Conversations.Moderation.Manage' => 'tinyint',
            'Conversations.Conversations.Add' => 'tinyint',
            'Vanilla.Approval.Require' => 'tinyint',
            'Vanilla.Comments.Me' => 'tinyint',
            'Vanilla.Discussions.View' => 'tinyint',
            'Vanilla.Discussions.Add' => 'tinyint',
            'Vanilla.Discussions.Edit' => 'tinyint',
            'Vanilla.Discussions.Announce' => 'tinyint',
            'Vanilla.Discussions.Sink' => 'tinyint',
            'Vanilla.Discussions.Close' => 'tinyint',
            'Vanilla.Discussions.Delete' => 'tinyint',
            'Vanilla.Comments.Add' => 'tinyint',
            'Vanilla.Comments.Edit' => 'tinyint',
            'Vanilla.Comments.Delete' => 'tinyint'
        ),
        'Poll' => array(
            'PollID' => 'int',
            'Name' => 'varchar(255)',
            'DiscussionID' => 'int',
            'CountOptions' => 'int',
            'CountVotes' => 'int',
            'Anonymous' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int'
        ),
        'PollOption' => array(
            'PollOptionID' => 'int',
            'PollID' => 'int',
            'Body' => 'varchar(500)',
            'Format' => 'varchar(20)',
            'Sort' => 'smallint',
            'CountVotes' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int'
        ),
        'PollVote' => array(
            'UserID' => 'int',
            'PollOptionID' => 'int'
        ),
        'Rank' => array(
            'RankID' => 'int',
            'Name' => 'varchar(100)',
            'Level' => 'smallint',
            'Label' => 'varchar(255)',
            'Body' => 'text',
            'Attributes' => 'text'
        ),
        'ReactionType' => array(
            'UrlCode' => 'varchar(32)',
            'Name' => 'varchar(32)',
            'Description' => 'text',
            'Class' => 'varchar(10)',
            'TagID' => 'int',
            'Attributes' => 'text',
            'Sort' => 'smallint',
            'Active' => 'tinyint',
            'Custom' => 'tinyint',
            'Hidden' => 'tinyint'
        ),
        'Regarding' => array(
            'RegardingID' => 'int',
            'Type' => 'varchar(255)',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'ForeignType' => 'varchar(32)',
            'ForeignID' => 'int',
            'OriginalContent' => 'text',
            'ParentType' => 'varchar(32)',
            'ParentID' => 'int',
            'ForeignURL' => 'varchar(255)',
            'Comment' => 'text',
            'Reports' => 'int'
        ),
        'Role' => array(
            'RoleID' => 'int',
            'Name' => 'varchar(100)',
            'Description' => 'varchar(500)',
            'Sort' => 'int',
            'Deletable' => 'tinyint',
            'CanSession' => 'tinyint',
            'PersonalInfo' => 'tinyint'
        ),
        'Session' => array(
            'SessionID' => 'char(32)',
            'UserID' => 'int',
            'DateInserted' => 'datetime',
            'DateUpdated' => 'datetime',
            'TransientKey' => 'varchar(12)',
            'Attributes' => 'text'
        ),
        'Spammer' => array(
            'UserID' => 'int',
            'CountSpam' => 'smallint',
            'CountDeletedSpam' => 'smallint'
        ),
        'Tag' => array(
            'TagID' => 'int',
            'Name' => 'varchar(255)',
            'FullName' => 'varchar(255)',
            'Type' => 'varchar(20) ',
            'ParentTagID' => 'int',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'CategoryID' => 'int',
            'CountDiscussions' => 'int'
        ),
        'TagDiscussion' => array(
            'TagID' => 'int',
            'DiscussionID' => 'int',
            'CategoryID' => 'int',
            'DateInserted' => 'datetime'
        ),
        'User' => array(
            'UserID' => 'int',
            'Name' => 'varchar(50)',
            'Password' => 'varbinary(100)',
            'HashMethod' => 'varchar(10)',
            'Photo' => 'varchar(255)',
            'Title' => 'varchar(100)',
            'Location' => 'varchar(100)',
            'About' => 'text',
            'Email' => 'varchar(200)',
            'ShowEmail' => 'tinyint',
            //'Gender' => array('u','m','f'),
            'CountVisits' => 'int',
            'CountInvitations' => 'int',
            'CountNotifications' => 'int',
            'InviteUserID' => 'int',
            'DiscoveryText' => 'text',
            'Preferences' => 'text',
            'Permissions' => 'text',
            'Attributes' => 'text',
            'DateSetInvitations' => 'datetime',
            'DateOfBirth' => 'datetime',
            'DateFirstVisit' => 'datetime',
            'DateLastActive' => 'datetime',
            'LastIPAddress' => 'varchar(15)',
            'AllIPAddresses' => 'varchar(100)',
            'DateInserted' => 'datetime',
            'InsertIPAddress' => 'varchar(15)',
            'DateUpdated' => 'datetime',
            'UpdateIPAddress' => 'varchar(15)',
            'HourOffset' => 'int',
            'Score' => 'float',
            'Admin' => 'tinyint',
            'Confirmed' => 'tinyint',
            'Verified' => 'tinyint',
            'Banned' => 'tinyint',
            'Deleted' => 'tinyint',
            'Points' => 'int',
            'CountUnreadConversations' => 'int',
            'CountDiscussions' => 'int',
            'CountUnreadDiscussions' => 'int',
            'CountComments' => 'int',
            'CountDrafts' => 'int',
            'CountBookmarks' => 'int',
            'RankID' => 'int'
        ),
        'UserAuthentication' => array(
            'ForeignUserKey' => 'varchar(255)',
            'ProviderKey' => 'varchar(64)',
            'UserID' => 'int'
        ),
        'UserAuthenticationNonce' => array(
            'Nonce' => 'varchar(200)',
            'Token' => 'varchar(128)',
            'Timestamp' => 'timestamp'
        ),
        'UserAuthenticationProvider' => array(
            'AuthenticationKey' => 'varchar(64)',
            'AuthenticationSchemeAlias' => 'varchar(32)',
            'Name' => 'varchar(50)',
            'URL' => 'varchar(255)',
            'AssociationSecret' => 'text',
            'AssociationHashMethod' => 'varchar(20)',
            'AuthenticateUrl' => 'varchar(255)',
            'RegisterUrl' => 'varchar(255)',
            'SignInUrl' => 'varchar(255)',
            'SignOutUrl' => 'varchar(255)',
            'PasswordUrl' => 'varchar(255)',
            'ProfileUrl' => 'varchar(255)',
            'Attributes' => 'text',
            'Active' => 'tinyint',
            'IsDefault' => 'tinyint'
        ),
        'UserAuthenticationToken' => array(
            'Token' => 'varchar(128)',
            'ProviderKey' => 'varchar(64)',
            'ForeignUserKey' => 'varchar(255)',
            'TokenSecret' => 'varchar(64)',
            //'TokenType' => array('request','access'),
            'Authorized' => 'tinyint',
            'Timestamp' => 'timestamp',
            'Lifetime' => 'int'
        ),
        'UserCategory' => array(
            'UserID' => 'int',
            'CategoryID' => 'int',
            'DateMarkedRead' => 'datetime',
            'Unfollow' => 'tinyint'
        ),
        'UserComment' => array(
            'UserID' => 'int',
            'CommentID' => 'int',
            'Score' => 'float',
            'DateLastViewed' => 'datetime'
        ),
        'UserConversation' => array(
            'UserID' => 'int',
            'ConversationID' => 'int',
            'CountReadMessages' => 'int',
            'LastMessageID' => 'int',
            'DateLastViewed' => 'datetime',
            'DateCleared' => 'datetime',
            'Bookmarked' => 'tinyint',
            'Deleted' => 'tinyint',
            'DateConversationUpdated' => 'datetime'
        ),
        'UserDiscussion' => array(
            'UserID' => 'int',
            'DiscussionID' => 'int',
            'Score' => 'float',
            'CountComments' => 'int',
            'DateLastViewed' => 'datetime',
            'Dismissed' => 'tinyint',
            'Bookmarked' => 'tinyint',
            'Participated' => 'tinyint'
        ),
        'UserEvent' => array(
            'EventID' => 'int',
            'UserID' => 'int',
            'DateInserted' => 'datetime',
            'Attending' => 'varchar(200)' // 'Yes', 'No', 'Maybe', 'Invited'
        ),
        'UserGroup' => array(
            'UserGroupID' => 'int',
            'GroupID' => 'int',
            'UserID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'Role' => 'varchar(255)' // 'Leader', 'Member'
        ),
        'UserMeta' => array(
            'UserID' => 'int',
            'Name' => 'varchar(255)',
            'Value' => 'text'
        ),
        'UserNote' => array(
            'UserNoteID' => 'int',
            'Type' => 'varchar(10)',
            'UserID' => 'int',
            'Body' => 'text',
            'Format' => 'varchar(10)',
            'RecordType' => 'varchar(20)',
            'RecordID' => 'int',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'InsertIPAddress' => 'varchar(15)',
            'UpdateUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateIPAddress' => 'varchar(15)',
            'Attributes' => 'text'
        ),
        'UserPoints' => array(
            //'SlotType' => array('d','w','m','y','a'),
            'TimeSlot' => 'datetime',
            'Source' => 'varchar(10)',
            'CategoryID' => 'int',
            'UserID' => 'int',
            'Points' => 'int'
        ),
        'UserRole' => array(
            'UserID' => 'int',
            'RoleID' => 'int'
        ),
        'UserTag' => array(
            'RecordType' => 'varchar(200)', //'Discussion', 'Discussion-Total', 'Comment', 'Comment-Total', 'User', 'User-Total', 'Activity', 'Activity-Total', 'ActivityComment', 'ActivityComment-Total'
            'RecordID' => 'int',
            'TagID' => 'int',
            'UserID' => 'int',
            'DateInserted' => 'datetime',
            'Total' => 'int'
        )
    );
}

?>
