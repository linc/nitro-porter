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
            'ActivityType' => 'varchar(20)',
            'ActivityUserID' => 'int',
            'RegardingUserID' => 'int',
            'NotifyUserID' => 'int',
            'HeadlineFormat' => 'varchar(255)',
            'Story' => 'text',
            'Format' => 'varchar(10)',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'InsertIPAddress' => 'varchar(15)'
            ),
      'Category' => array(
            'CategoryID' => 'int',
            'Name' => 'varchar(30)',
            'UrlCode' => 'varchar(255)',
            'Description' => 'varchar(250)',
            'ParentCategoryID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'Sort' => 'int',
            'Archived' => 'tinyint(1)'
            ),
      'Comment' => array(
            'CommentID' => 'int',
            'DiscussionID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'InsertIPAddress' => 'varchar(15)',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'UpdateIPAddress' => 'varchar(15)',
            'Format' => 'varchar(20)',
            'Body' => 'text',
            'Score' => 'float'
            ),
      'Conversation' => array(
            'ConversationID' => 'int',
            'Subject' => 'varchar(255)',
            'FirstMessageID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int'
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
            'Name' => 'varchar(100)',
            'Body' => 'text',
            'Format' => 'varchar(20)',
            'CategoryID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'InsertIPAddress' => 'varchar(15)',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'UpdateIPAddress' => 'varchar(15)',
            'DateLastComment' => 'datetime',
            'CountComments' => 'int',
            'CountViews' => 'int',
            'Score' => 'float',
            'Closed' => 'tinyint',
            'Announce' => 'tinyint',
            'Sink' => 'tinyint',
            'Type' => 'varchar(20)'
            ),
      'Media' => array(
            'MediaID' => 'int',
            'Name' => 'varchar(255)',
            'Type' => 'varchar(128)',
            'Size' => 'int',
            'StorageMethod' => 'varchar(24)',
            'Path' => 'varchar(255)',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'ForeignID' => 'int',
            'ForeignTable' => 'varchar(24)',
            'ImageWidth' => 'int',
            'ImageHeight' => 'int'
            ),
      'Permission' => array(
            'RoleID' => 'int',
            'JunctionTable' => 'varchar(100)',
            'JunctionColumn' => 'varchar(100)',
            'JunctionID' => 'int',
            '_Permissions' => 'varchar(255)',
            'Garden.SignIn.Allow' => 'tinyint',
            'Garden.Activity.View' => 'tinyint',
            'Garden.Profiles.View' => 'tinyint',
            'Vanilla.Discussions.View' => 'tinyint',
            'Vanilla.Discussions.Add' => 'tinyint',
            'Vanilla.Comments.Add' => 'tinyint'
            ),
      'Poll' => array(
            'PollID' => 'int',
            'Name' => 'varchar(255)',
            'DiscussionID' => 'int',
            'Anonymous' => 'tinyint',
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
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int'
            ),
      'PollVote' => array(
            'UserID' => 'int',
            'PollOptionID' => 'int',
            'DateInserted' => 'datetime'
            ),
      'Rank' => array(
            'RankID' => 'int',
            'Name' => 'varchar(100)',
            'Level' => 'smallint',
            'Label' => 'varchar(255)',
            'Body' => 'text',
            'Attributes' => 'text'
            ),
      'Role' => array(
            'RoleID' => 'int',
            'Name' => 'varchar(100)',
            'Description' => 'varchar(200)',
            'CanSession' => 'tinyint'
            ),
      'Tag' => array(
            'TagID' => 'int',
            'Name' => 'varchar(255)',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime'
            ),
      'TagDiscussion' => array(
            'TagID' => 'int',
            'DiscussionID' => 'int'
            ),
      'User' => array(
            'UserID' => 'int',
            'Name' => 'varchar(20)',
            'Email' => 'varchar(200)',
            'Password' => 'varbinary(100)',
            'HashMethod' => 'varchar(10)',
            //'Gender' => array('m', 'f'),
            'Title' => 'varchar(100)',
            'Location' => 'varchar(100)',
            'Score' => 'float',
            'InviteUserID' => 'int',
            'HourOffset' => 'int',
            'CountDiscussions' => 'int',
            'CountComments' => 'int',
            'DiscoveryText' => 'text',
            'Photo' => 'varchar(255)',
            'DateOfBirth' => 'datetime',
            'DateFirstVisit' => 'datetime',
            'DateLastActive' => 'datetime',
            'DateInserted' => 'datetime',
            'InsertIPAddress' => 'varchar(15)',
            'LastIPAddress' => 'varchar(15)',
            'DateUpdated' => 'datetime',
            'Banned' => 'tinyint',
            'ShowEmail' => 'tinyint',
            'RankID' => 'int'
            ),
      'UserAuthentication' => array(
            'ForeignUserKey' => 'varchar(255)',
            'ProviderKey' => 'varchar(64)',
            'UserID' => 'varchar(11)',
            'Attributes' => 'text'
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
            'Deleted' => 'tinyint(1)',
            'LastMessageID' => 'int'
            ),
      'UserDiscussion' => array(
            'UserID' => 'int',
            'DiscussionID' => 'int',
            'Bookmarked' => 'tinyint',
            'DateLastViewed' => 'datetime',
            'CountComments' => 'int'
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
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime',
            'InsertIPAddress' => 'varchar(15)'
            ),
      'UserRole' => array(
            'UserID' => 'int',
            'RoleID' => 'int'
            ),
      'Ban' => array(
            'BanID' => 'int',
            'BanType' => 'varchar(50)',
            'BanValue' => 'varchar(50)',
            'Notes' => 'varchar(255)',
            'CountUsers' => 'int',
            'CountBlockRegistrations' => 'int',
            'InsertUserID' => 'int',
            'DateInserted' => 'datetime'
            ),
      'Group' => array(
            'GroupID' => 'int',
            'Name' => 'varchar(255)',
            'Description' => 'text',
            'Format' => 'varchar(10)',
            'CategoryID' => 'int',
            'Icon' => 'varchar(255)',
            'Banner' => 'varchar(255)',
            'Privacy' => 'varchar(255)',
            'Registration' => 'varchar(255)',
            'Visibility' => 'varchar(255)',
            'CountMembers' => 'int',
            'CountDiscussions' => 'int',
            'DateLastComment' => 'datetime',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'DateUpdated' => 'datetime',
            'UpdateUserID' => 'int',
            'Attributes' => 'text'
            ),
      'UserGroup' => array(
            'UserGroupID' => 'int',
            'GroupID' => 'int',
            'UserID' => 'int',
            'DateInserted' => 'datetime',
            'InsertUserID' => 'int',
            'Role' => 'varchar(255)'
            ),
   );
}

?>