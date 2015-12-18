<?php
/**
 * Vanilla 1 exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['vanilla1'] = array('name' => 'Vanilla 1', 'prefix' => 'LUM_');
$Supported['vanilla1']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Attachments' => 1,
    'PrivateMessages' => 1,
    'Passwords' => 1,
    'Bookmarks' => 1,
);

class Vanilla1 extends ExportController {

    /** @var array Required tables => columns */
    public $SourceTables = array(
        'User' => array('UserID', 'Name', 'Password', 'Email', 'CountComments'),
        'Role' => array('RoleID', 'Name', 'Description'),
        'Category' => array('CategoryID', 'Name', 'Description'),
        'Discussion' => array(
            'DiscussionID',
            'Name',
            'CategoryID',
            'DateCreated',
            'AuthUserID',
            'DateLastActive',
            'Closed',
            'Sticky',
            'CountComments',
            'Sink',
            'LastUserID'
        ),
        'Comment' => array(
            'CommentID',
            'DiscussionID',
            'AuthUserID',
            'DateCreated',
            'EditUserID',
            'DateEdited',
            'Body',
            'Deleted'
        )
    );

    /**
     * Forum-specific export format
     * @todo Project file size / export time and possibly break into multiple files
     * @param ExportModel $Ex
     *
     */
    protected function ForumExport($Ex) {
        $this->Ex = $Ex;

        $CharacterSet = $Ex->GetCharacterSet('Comment');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        // Begin
        $Ex->BeginExport('', 'Vanilla 1.*');

        // Users
        $User_Map = array(
            'UserID' => 'UserID',
            'Name' => 'Name',
            'Password' => 'Password',
            'Email' => 'Email',
            'Icon' => 'Photo',
            'CountComments' => 'CountComments',
            'Discovery' => 'DiscoveryText'
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
            'RoleID' => 'RoleID',
            'Name' => 'Name',
            'Description' => 'Description'
        );
        $Ex->ExportTable('Role',
            "select RoleID, Name, Description from :_Role union all select $ZeroRoleID, 'Applicant', 'Created by the Vanilla Porter'",
            $Role_Map);

        $Permission_Map = array(
            'RoleID' => 'RoleID',
            'PERMISSION_SIGN_IN' => 'Garden.SignIn.Allow',
            'Permissions' => array(
                'Column' => 'Vanilla.Comments.Add',
                'Type' => 'tinyint',
                'Filter' => array($this, 'FilterPermissions')
            ),
            'PERMISSION_START_DISCUSSION' => array(
                'Column' => 'Vanilla.Discussions.Add',
                'Type' => 'tinyint',
                'Filter' => array($this, 'ForceBool')
            ),
            'PERMISSION_SINK_DISCUSSION' => array(
                'Column' => 'Vanilla.Discussions.Sink',
                'Type' => 'tinyint',
                'Filter' => array($this, 'ForceBool')
            ),
            'PERMISSION_STICK_DISCUSSIONS' => array(
                'Column' => 'Vanilla.Discussions.Announce',
                'Type' => 'tinyint',
                'Filter' => array($this, 'ForceBool')
            ),
            'PERMISSION_CLOSE_DISCUSSIONS' => array(
                'Column' => 'Vanilla.Discussions.Close',
                'Type' => 'tinyint',
                'Filter' => array($this, 'ForceBool')
            ),
            'PERMISSION_EDIT_DISCUSSIONS' => array(
                'Column' => 'Vanilla.Discussions.Edit',
                'Type' => 'tinyint',
                'Filter' => array($this, 'ForceBool')
            ),
            'PERMISSION_EDIT_COMMENTS' => array(
                'Column' => 'Vanilla.Comments.Edit',
                'Type' => 'tinyint',
                'Filter' => array($this, 'ForceBool')
            ),
            'PERMISSION_APPROVE_APPLICANTS' => array(
                'Column' => 'Garden.Moderation.Manage',
                'Type' => 'tinyint',
                'Filter' => array($this, 'ForceBool')
            ),
            'PERMISSION_EDIT_USERS' => array(
                'Column' => 'Garden.Users.Edit',
                'Type' => 'tinyint',
                'Filter' => array($this, 'ForceBool')
            ),
            'PERMISSION_CHANGE_APPLICATION_SETTINGS' => array(
                'Column' => 'Garden.Settings.Manage',
                'Type' => 'tinyint',
                'Filter' => array($this, 'ForceBool')
            )
        );
        $Ex->ExportTable('Permission', "select * from :_Role", $Permission_Map);

        // UserRoles
        /*
            'UserID' => 'int',
            'RoleID' => 'int'
         */
        $UserRole_Map = array(
            'UserID' => 'UserID',
            'RoleID' => 'RoleID'
        );
        $Ex->ExportTable('UserRole',
            "select UserID, case RoleID when 0 then $ZeroRoleID else RoleID end as RoleID from :_User", $UserRole_Map);

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
            'Description' => 'Description'
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
            'CategoryID' => 'CategoryID',
            'DateCreated' => 'DateInserted',
            'DateCreated2' => 'DateUpdated',
            'AuthUserID' => 'InsertUserID',
            'DateLastActive' => 'DateLastComment',
            'AuthUserID2' => 'UpdateUserID',
            'Closed' => 'Closed',
            'Sticky' => 'Announce',
            'CountComments' => 'CountComments',
            'Sink' => 'Sink',
            'LastUserID' => 'LastCommentUserID'
        );
        $Ex->ExportTable('Discussion',
            "SELECT d.*,
            d.LastUserID as LastCommentUserID,
            d.DateCreated as DateCreated2, d.AuthUserID as AuthUserID2,
            c.Body,
            c.FormatType as Format
         FROM :_Discussion d
         LEFT JOIN :_Comment c
            ON d.FirstCommentID = c.CommentID
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
         WHERE d.FirstCommentID <> c.CommentID
            AND c.Deleted = '0'
            AND coalesce(d.WhisperUserID, 0) = 0
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

        // Create a mapping tables for conversations.
        // These mapping tables are used to group comments that a) are in the same discussion and b) are from and to the same users.

        $Ex->Query("drop table if exists z_pmto");

        $Ex->Query("create table z_pmto (
  CommentID int,
  UserID int,
  primary key(CommentID, UserID)
 )");

        $Ex->Query("insert ignore z_pmto (
  CommentID,
  UserID
)
select distinct
  CommentID,
  AuthUserID
from :_Comment
where coalesce(WhisperUserID, 0) <> 0");

        $Ex->Query("insert ignore z_pmto (
  CommentID,
  UserID
)
select distinct
  CommentID,
  WhisperUserID
from :_Comment
where coalesce(WhisperUserID, 0) <> 0");

        $Ex->Query("insert ignore z_pmto (
  CommentID,
  UserID
)
select distinct
  c.CommentID,
  d.AuthUserID
from :_Discussion d
join :_Comment c
  on c.DiscussionID = d.DiscussionID
where coalesce(d.WhisperUserID, 0) <> 0");

        $Ex->Query("insert ignore z_pmto (
  CommentID,
  UserID
)
select distinct
  c.CommentID,
  d.WhisperUserID
from :_Discussion d
join :_Comment c
  on c.DiscussionID = d.DiscussionID
where coalesce(d.WhisperUserID, 0) <> 0");

        $Ex->Query("insert ignore z_pmto (
  CommentID,
  UserID
)
select distinct
  c.CommentID,
  c.AuthUserID
from :_Discussion d
join :_Comment c
  on c.DiscussionID = d.DiscussionID
where coalesce(d.WhisperUserID, 0) <> 0");

        $Ex->Query("drop table if exists z_pmto2");

        $Ex->Query("create table z_pmto2 (
  CommentID int,
  UserIDs varchar(250),
  primary key (CommentID)
)");

        $Ex->Query("insert z_pmto2 (
  CommentID,
  UserIDs
)
select
  CommentID,
  group_concat(UserID order by UserID)
from z_pmto
group by CommentID");


        $Ex->Query("drop table if exists z_pm");

        $Ex->Query("create table z_pm (
  CommentID int,
  DiscussionID int,
  UserIDs varchar(250),
  GroupID int
)");

        $Ex->Query("insert ignore z_pm (
  CommentID,
  DiscussionID
)
select
  CommentID,
  DiscussionID
from :_Comment
where coalesce(WhisperUserID, 0) <> 0");

        $Ex->Query("insert ignore z_pm (
  CommentID,
  DiscussionID
)
select
  c.CommentID,
  c.DiscussionID
from :_Discussion d
join :_Comment c
  on c.DiscussionID = d.DiscussionID
where coalesce(d.WhisperUserID, 0) <> 0");

        $Ex->Query("update z_pm pm
join z_pmto2 t
  on t.CommentID = pm.CommentID
set pm.UserIDs = t.UserIDs");

        $Ex->Query("drop table if exists z_pmgroup");

        $Ex->Query("create table z_pmgroup (
  GroupID int,
  DiscussionID int,
  UserIDs varchar(250)
)");

        $Ex->Query("insert z_pmgroup (
  GroupID,
  DiscussionID,
  UserIDs
)
select
  min(pm.CommentID),
  pm.DiscussionID,
  t2.UserIDs
from z_pm pm
join z_pmto2 t2
  on pm.CommentID = t2.CommentID
group by pm.DiscussionID, t2.UserIDs");

        $Ex->Query("create index z_idx_pmgroup on z_pmgroup (DiscussionID, UserIDs)");

        $Ex->Query("create index z_idx_pmgroup2 on z_pmgroup (GroupID)");

        $Ex->Query("update z_pm pm
join z_pmgroup g
  on pm.DiscussionID = g.DiscussionID and pm.UserIDs = g.UserIDs
set pm.GroupID = g.GroupID");

        $Conversation_Map = array(
            'AuthUserID' => 'InsertUserID',
            'DateCreated' => 'DateInserted',
            'DiscussionID' => array('Column' => 'DiscussionID', 'Type' => 'int'),
            'CommentID' => 'ConversationID',
            'Name' => array('Column' => 'Subject', 'Type' => 'varchar(255)')
        );
        $Ex->ExportTable('Conversation',
            "select c.*, d.Name
from :_Comment c
join :_Discussion d
  on d.DiscussionID = c.DiscussionID
join z_pmgroup g
  on g.GroupID = c.CommentID;", $Conversation_Map);

        // ConversationMessage.
        $ConversationMessage_Map = array(
            'CommentID' => 'MessageID',
            'GroupID' => 'ConversationID',
            'Body' => 'Body',
            'FormatType' => 'Format',
            'AuthUserID' => 'InsertUserID',
            'DateCreated' => 'DateInserted'
        );
        $Ex->ExportTable('ConversationMessage',
            "select c.*, pm.GroupID
from z_pm pm
join :_Comment c
  on pm.CommentID = c.CommentID", $ConversationMessage_Map);

        // UserConversation
        /*
           'UserID' => 'int',
           'ConversationID' => 'int',
           'LastMessageID' => 'int'
        */
        $UserConversation_Map = array(
            'UserID' => 'UserID',
            'GroupID' => 'ConversationID'
        );
        $Ex->ExportTable('UserConversation',
            "select distinct
  pm.GroupID,
  t.UserID
from z_pmto t
join z_pm pm
  on pm.CommentID = t.CommentID", $UserConversation_Map);

        $Ex->Query("drop table z_pmto");
        $Ex->Query("drop table z_pmto2");
        $Ex->Query("drop table z_pm");
        $Ex->Query("drop table z_pmgroup");

        // Media
        if ($Ex->Exists('Attachment')) {
            $Media_Map = array(
                'AttachmentID' => 'MediaID',
                'Name' => 'Name',
                'MimeType' => 'Type',
                'Size' => 'Size',
                'Path' => array('Column' => 'Path', 'Filter' => array($this, 'StripMediaPath')),
                'UserID' => 'InsertUserID',
                'DateCreated' => 'DateInserted',
                'CommentID' => 'ForeignID'
                //'ForeignTable'
            );
            $Ex->ExportTable('Media',
                "select a.*, 'comment' as ForeignTable from :_Attachment a",
                $Media_Map);
        }

        // End
        $Ex->EndExport();
    }

    public function StripMediaPath($AbsPath) {
        if (($Pos = strpos($AbsPath, '/uploads/')) !== false) {
            return substr($AbsPath, $Pos + 9);
        }

        return $AbsPath;
    }

    public function FilterPermissions($Permissions, $ColumnName, &$Row) {
        $Permissions2 = unserialize($Permissions);

        foreach ($Permissions2 as $Name => $Value) {
            if (is_null($Value)) {
                $Permissions2[$Name] = false;
            }
        }

        if (is_array($Permissions2)) {
            $Row = array_merge($Row, $Permissions2);
            $this->Ex->CurrentRow = $Row;

            return isset($Permissions2['PERMISSION_ADD_COMMENTS']) ? $Permissions2['PERMISSION_ADD_COMMENTS'] : false;
        }

        return false;
    }

    public function ForceBool($Value) {
        if ($Value) {
            return true;
        }

        return false;
    }
}
