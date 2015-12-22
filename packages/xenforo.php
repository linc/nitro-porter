<?php
/**
 * Xenforo exporter tool.
 *
 * To export avatars, provide ?avatars=1&folder=/path/to/avatars
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
$Supported['xenforo'] = array('name' => 'Xenforo', 'prefix' => 'xf_');
$Supported['xenforo']['CommandLine'] = array(
    'avatarpath' => array('Full path of source avatars to process.', 'Sx' => ':', 'Field' => 'avatarpath'),
);
$Supported['xenforo']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'Passwords' => 1,
    'PrivateMessages' => 1,
    'Permissions' => 1,
    'Signatures' => 1,
);

class Xenforo extends ExportController {

    protected $Processed;
    protected $SourceFolder;
    protected $TargetFolder;
    protected $Folders;
    protected $Types;

    /**
     * Export avatars into vanilla-compatibles names
     */
    public function doAvatars() {

        // Check source folder
        $this->SourceFolder = $this->param('avatarpath');
        if (!is_dir($this->SourceFolder)) {
            trigger_error("Source avatar folder '{$this->SourceFolder}' does not exist.");
        }

        // Set up a target folder
        $this->TargetFolder = combinePaths(array($this->SourceFolder, 'xf'));
        if (!is_dir($this->TargetFolder)) {
            @$Made = mkdir($this->TargetFolder, 0777, true);
            if (!$Made) {
                trigger_error("Target avatar folder '{$this->TargetFolder}' could not be created.");
            }
        }

        // Iterate
        $this->Folders = array(
            'Thumb' => 'm',
            'Profile' => 'l'
        );

        $this->Types = array(
            'Thumb' => 'n',
            'Profile' => 'p'
        );

        foreach ($this->Folders as $Type => $Folder) {

            $this->Processed = 0;
            $Errors = array();

            $TypeSourceFolder = combinePaths(array($this->SourceFolder, $Folder));
            echo "Processing '{$Type}' files in {$TypeSourceFolder}:\n";
            $this->avatarFolder($TypeSourceFolder, $Type, $Errors);

            $nErrors = sizeof($Errors);
            if ($nErrors) {
                echo "{$nErrors} errors:\n";
                foreach ($Errors as $Error) {
                    echo "{$Error}\n";
                }
            }

        }
    }

    protected function avatarFolder($Folder, $Type, &$Errors) {
        if (!is_dir($Folder)) {
            trigger_error("Target avatar folder '{$Folder}' does not exist.");
        }
        $ResFolder = opendir($Folder);

        $Errors = array();
        while (($File = readdir($ResFolder)) !== false) {
            if ($File == '.' || $File == '..') {
                continue;
            }

            $FullPath = combinePaths(array($Folder, $File));

            // Folder? Recurse
            if (is_dir($FullPath)) {
                $this->avatarFolder($FullPath, $Type, $Errors);
                continue;
            }

            $this->Processed++;

            // Determine target paths and name
            $Photo = trim($File);
            $PhotoSrc = combinePaths(array($Folder, $Photo));
            $PhotoFileName = basename($PhotoSrc);
            $PhotoPath = dirname($PhotoSrc);

            $StubFolder = getValue($Type, $this->Folders);
            $TrimFolder = combinePaths(array($this->SourceFolder, $StubFolder));
            $PhotoPath = str_replace($TrimFolder, '', $PhotoPath);
            $PhotoFolder = combinePaths(array($this->TargetFolder, $PhotoPath));
            @mkdir($PhotoFolder, 0777, true);

            if (!file_exists($PhotoSrc)) {
                $Errors[] = "Missing file: {$PhotoSrc}";
                continue;
            }

            $TypePrefix = getValue($Type, $this->Types);
            $PhotoDest = combinePaths(array($PhotoFolder, "{$TypePrefix}{$PhotoFileName}"));
            $Copied = @copy($PhotoSrc, $PhotoDest);
            if (!$Copied) {
                $Errors[] = "! failed to copy photo '{$PhotoSrc}' (-> {$PhotoDest}).";
            }

            if (!($this->Processed % 100)) {
                echo " - processed {$this->Processed}\n";
            }
        }
    }

    /*
     * Forum-specific export format.
     * @param ExportModel $Ex
     */

    protected function forumExport($Ex) {
        $this->Ex = $Ex;

        $Cdn = $this->cdnPrefix();

        $CharacterSet = $Ex->getCharacterSet('posts');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        $Ex->SourcePrefix = 'xf_';
//      $Ex->UseCompression(FALSE);
        // Begin
        $Ex->beginExport('', 'xenforo', array('HashMethod' => 'xenforo'));

        // Export avatars
        if ($this->param('avatars')) {
            $this->doAvatars();
        }

        // Users.
        $User_Map = array(
            'user_id' => 'UserID',
            'username' => 'Name',
            'email' => 'Email',
            'gender' => array(
                'Column' => 'Gender',
                'Filter' => function ($Value) {
                    switch ($Value) {
                        case 'male':
                            return 'm';
                        case 'female':
                            return 'f';
                        default:
                            return 'u';
                    }
                }
            ),
            'custom_title' => 'Title',
            'register_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'last_activity' => array('Column' => 'DateLastActive', 'Filter' => 'TimestampToDate'),
            'is_admin' => 'Admin',
            'is_banned' => 'Banned',
            'password' => 'Password',
            'hash_method' => 'HashMethod',
            'avatar' => 'Photo'
        );
        $Ex->exportTable('User', "
         select
            u.*,
            ua.data as password,
            'xenforo' as hash_method,
            case when u.avatar_date > 0 then concat('{$Cdn}xf/', u.user_id div 1000, '/', u.user_id, '.jpg') else null end as avatar
         from :_user u
         left join :_user_authenticate ua
            on u.user_id = ua.user_id", $User_Map);

        // Roles.
        $Role_Map = array(
            'user_group_id' => 'RoleID',
            'title' => 'Name'
        );
        $Ex->exportTable('Role', "
         select *
         from :_user_group", $Role_Map);

        // User Roles.
        $UserRole_Map = array(
            'user_id' => 'UserID',
            'user_group_id' => 'RoleID'
        );

        $Ex->exportTable('UserRole', "
         select user_id, user_group_id
         from :_user

         union all

         select u.user_id, ua.user_group_id
         from :_user u
         join :_user_group ua
            on find_in_set(ua.user_group_id, u.secondary_group_ids)", $UserRole_Map);

        // Permission.
        $this->exportPermissions();

        // User Meta.
        $this->exportUserMeta();

        // Categories.
        $Category_Map = array(
            'node_id' => 'CategoryID',
            'title' => 'Name',
            'description' => 'Description',
            'parent_node_id' => array(
                'Column' => 'ParentCategoryID',
                'Filter' => function ($Value) {
                    return $Value ? $Value : null;
                }
            ),
            'display_order' => 'Sort',
            'display_in_list' => array('Column' => 'HideAllDiscussions', 'Filter' => 'NotFilter')
        );
        $Ex->exportTable('Category', "
         select n.*
         from :_node n
         ", $Category_Map);

        // Discussions.
        $Discussion_Map = array(
            'thread_id' => 'DiscussionID',
            'node_id' => 'CategoryID',
            'title' => 'Name',
            'view_count' => 'CountViews',
            'user_id' => 'InsertUserID',
            'post_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'sticky' => 'Announce',
            'discussion_open' => array('Column' => 'Closed', 'Filter' => 'NotFilter'),
            'last_post_date' => array('Column' => 'DateLastComment', 'Filter' => 'TimestampToDate'),
            'message' => 'Body',
            'format' => 'Format',
            'ip' => array('Column' => 'InsertIPAddress', 'Filter' => 'long2ipf')
        );
        $Ex->exportTable('Discussion', "
         select
            t.*,
            p.message,
            'BBCode' as format,
            ip.ip
         from :_thread t
         join :_post p
            on t.first_post_id = p.post_id
         left join :_ip ip
            on p.ip_id = ip.ip_id", $Discussion_Map);


        // Comments.
        $Comment_Map = array(
            'post_id' => 'CommentID',
            'thread_id' => 'DiscussionID',
            'user_id' => 'InsertUserID',
            'post_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'message' => 'Body',
            'format' => 'Format',
            'ip' => array('Column' => 'InsertIPAddress', 'Filter' => 'long2ipf')
        );
        $Ex->exportTable('Comment', "
         select
            p.*,
            'BBCode' as format,
            ip.ip
         from :_post p
         join :_thread t
            on p.thread_id = t.thread_id
         left join :_ip ip
            on p.ip_id = ip.ip_id
         where p.post_id <> t.first_post_id
            and message_state = 'visible'", $Comment_Map);

        // Conversation.
        $Conversation_Map = array(
            'conversation_id' => 'ConversationID',
            'title' => 'Subject',
            'user_id' => 'InsertUserID',
            'start_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate')
        );
        $Ex->exportTable('Conversation', "
         select *
         from :_conversation_master", $Conversation_Map);

        $ConversationMessage_Map = array(
            'message_id' => 'MessageID',
            'conversation_id' => 'ConversationID',
            'message_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'user_id' => 'InsertUserID',
            'message' => 'Body',
            'format' => 'Format',
            'ip' => array('Column' => 'InsertIPAddress', 'Filter' => 'long2ipf')
        );
        $Ex->exportTable('ConversationMessage', "
         select
            m.*,
            'BBCode' as format,
            ip.ip
         from :_conversation_message m
         left join :_ip ip
            on m.ip_id = ip.ip_id", $ConversationMessage_Map);

        $UserConversation_Map = array(
            'conversation_id' => 'ConversationID',
            'user_id' => 'UserID',
            'Deleted' => 'Deleted'
        );
        $Ex->exportTable('UserConversation', "
         select
            r.conversation_id,
            user_id,
            case when r.recipient_state = 'deleted' then 1 else 0 end as Deleted
         from :_conversation_recipient r

         union all

         select
            cu.conversation_id,
            cu.owner_user_id,
            0
         from :_conversation_user cu
         ", $UserConversation_Map);

        $Ex->endExport();
    }

    public function exportPermissions() {
        $Ex = $this->Ex;

        $Permissions = array();

        // Export the global permissions.
        $r = $Ex->query("
         select
            pe.*,
            g.title
         from :_permission_entry pe
         join :_user_group g
            on pe.user_group_id = g.user_group_id");
        $this->_exportPermissions($r, $Permissions);

        $r = $Ex->query("
          select
            pe.*,
            g.title
         from :_permission_entry_content pe
         join :_user_group g
            on pe.user_group_id = g.user_group_id");
        $this->_exportPermissions($r, $Permissions);


        if (count($Permissions) == 0) {
            return;
        }

        $Permissions = array_values($Permissions);

        // Now that we have all of the permission in an array let's export them.
        $Columns = $this->_exportPermissions(false);

        foreach ($Columns as $Index => $Column) {
            if (strpos($Column, '.') !== false) {
                $Columns[$Index] = array('Column' => $Column, 'Type' => 'tinyint');
            }
        }
        $Structure = $Ex->getExportStructure($Columns, 'Permission', $Columns, 'Permission');
        $RevMappings = $Ex->flipMappings($Columns);

        $Ex->writeBeginTable($Ex->File, 'Permission', $Structure);
        $count = 0;
        foreach ($Permissions as $Row) {
            $Ex->writeRow($Ex->File, $Row, $Structure, $RevMappings);
            $count++;
        }
        $Ex->writeEndTable($Ex->File);
        $Ex->comment("Exported Table: Permission ($count rows)");

//       var_export($Permissions);
    }

    public function exportUserMeta() {
        $Ex = $this->Ex;

        $Sql = "
         select
           user_id as UserID,
           'Plugin.Signatures.Sig' as Name,
           signature as Value
         from :_user_profile
         where nullif(signature, '') is not null

         union

         select
           user_id,
           'Plugin.Signatures.Format',
           'BBCode'
         from :_user_profile
         where nullif(signature, '') is not null";

        $Ex->exportTable('UserMeta', $Sql);
    }

    protected function _exportPermissions($r, &$Perms = null) {
        $Map = array(
            'general.viewNode' => 'Vanilla.Discussions.View',
            'forum.deleteAnyPost' => 'Vanilla.Comments.Delete',
            'forum.deleteAnyThread' => 'Vanilla.Discussions.Delete',
            'forum.editAnyPost' => array('Vanilla.Discussions.Edit', 'Vanilla.Comments.Edit'),
            'forum.lockUnlockThread' => 'Vanilla.Discussions.Close',
            'forum.postReply' => array('Vanilla.Comments.Add'),
            'forum.postThread' => 'Vanilla.Discussions.Add',
            'forum.stickUnstickThread' => array('Vanilla.Discussions.Announce', 'Vanilla.Discussions.Sink'),
            'forum.uploadAttachment' => 'Plugins.Attachments.Upload.Allow',
            'forum.viewAttachment' => 'Plugins.Attachments.Download.Allow',
            'general.editSignature' => 'Plugins.Signatures.Edit',
            'general.viewProfile' => 'Garden.Profiles.View',
            'profilePost.deleteAny' => 'Garden.Activity.Delete',
            'profilePost.post' => array('Garden.Email.View', 'Garden.SignIn.Allow', 'Garden.Profiles.Edit')
        );

        if ($r === false) {
            $Result = array(
                'RoleID' => 'RoleID',
                'JunctionTable' => 'JunctionTable',
                'JunctionColumn' => 'JunctionColumn',
                'JunctionID' => 'JunctionID',
                '_Permissions' => '_Permissions',
                'Garden.Moderation.Manage' => 'Garden.Moderation.Manage'
            );

            // Return an array of fieldnames.
            foreach ($Map as $Columns) {
                $Columns = (array)$Columns;
                foreach ($Columns as $Column) {
                    $Result[$Column] = $Column;
                }
            }

            return $Result;
        }

        while ($row = mysql_fetch_assoc($r)) {
            $RoleID = $row['user_group_id'];

            $Perm = "{$row['permission_group_id']}.{$row['permission_id']}";

            if (!isset($Map[$Perm])) {
                continue;
            }

            $Names = (array)$Map[$Perm];

            foreach ($Names as $Name) {
                if (isset($row['content_id'])) {
                    if ($row['content_type'] != 'node') {
                        continue;
                    }

                    $CategoryID = $row['content_id'];
                } else {
                    $CategoryID = null;
                }

                // Is this a per-category permission?
                if (strpos($Name, 'Vanilla.Discussions.') !== false || strpos($Name, 'Vanilla.Comments.') !== false) {
                    if (!$CategoryID) {
                        $CategoryID = -1;
                    }
                } else {
                    $CategoryID = null;
                }


                $Key = "{$RoleID}_{$CategoryID}";

                $Perms[$Key]['RoleID'] = $RoleID;
                $PermRow = &$Perms[$Key];
                if ($CategoryID) {
                    $PermRow['JunctionTable'] = 'Category';
                    $PermRow['JunctionColumn'] = 'PermissionCategoryID';
                    $PermRow['JunctionID'] = $CategoryID;
                }

                $Title = $row['title'];
                $PermRow['Title'] = $Title;
                if (stripos($Title, 'Admin') !== false) {
                    $PermRow['_Permissions'] = 'all';
                }
                if (!$CategoryID && stripos($Title, 'Mod') !== false) {
                    $PermRow['Garden.Moderation.Manage'] = true;
                }

                // Set all of the permissions.
                $PermValue = $row['permission_value'];
                if ($PermValue == 'deny') {
                    $PermRow[$Name] = false;
                } elseif (in_array($PermValue, array('allow', 'content_allow'))) {
                    if (!isset($PermRow[$Name]) || $PermRow[$Name] !== false) {
                        $PermRow[$Name] = true;
                    }
                } elseif (!isset($PermRow[$Name])) {
                    $PermRow[$Name] = null;
                }
            }
        }
    }

}
