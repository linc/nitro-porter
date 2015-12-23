<?php
/**
 * Xenforo exporter tool.
 *
 * To export avatars, provide ?avatars=1&folder=/path/to/avatars
 *
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['xenforo'] = array('name' => 'Xenforo', 'prefix' => 'xf_');
$supported['xenforo']['CommandLine'] = array(
    'avatarpath' => array('Full path of source avatars to process.', 'Sx' => ':', 'Field' => 'avatarpath'),
);
$supported['xenforo']['features'] = array(
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

    protected $processed;
    protected $sourceFolder;
    protected $targetFolder;
    protected $folders;
    protected $types;

    /**
     * Export avatars into vanilla-compatibles names
     */
    public function doAvatars() {

        // Check source folder
        $this->sourceFolder = $this->param('avatarpath');
        if (!is_dir($this->sourceFolder)) {
            trigger_error("Source avatar folder '{$this->sourceFolder}' does not exist.");
        }

        // Set up a target folder
        $this->targetFolder = combinePaths(array($this->sourceFolder, 'xf'));
        if (!is_dir($this->targetFolder)) {
            @$made = mkdir($this->targetFolder, 0777, true);
            if (!$made) {
                trigger_error("Target avatar folder '{$this->targetFolder}' could not be created.");
            }
        }

        // Iterate
        $this->folders = array(
            'Thumb' => 'm',
            'Profile' => 'l'
        );

        $this->types = array(
            'Thumb' => 'n',
            'Profile' => 'p'
        );

        foreach ($this->folders as $type => $folder) {

            $this->processed = 0;
            $errors = array();

            $typeSourceFolder = combinePaths(array($this->sourceFolder, $folder));
            echo "Processing '{$type}' files in {$typeSourceFolder}:\n";
            $this->avatarFolder($typeSourceFolder, $type, $errors);

            $nErrors = sizeof($errors);
            if ($nErrors) {
                echo "{$nErrors} errors:\n";
                foreach ($errors as $error) {
                    echo "{$error}\n";
                }
            }

        }
    }

    protected function avatarFolder($folder, $type, &$errors) {
        if (!is_dir($folder)) {
            trigger_error("Target avatar folder '{$folder}' does not exist.");
        }
        $resFolder = opendir($folder);

        $errors = array();
        while (($file = readdir($resFolder)) !== false) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            $fullPath = combinePaths(array($folder, $file));

            // Folder? Recurse
            if (is_dir($fullPath)) {
                $this->avatarFolder($fullPath, $type, $errors);
                continue;
            }

            $this->processed++;

            // Determine target paths and name
            $photo = trim($file);
            $photoSrc = combinePaths(array($folder, $photo));
            $photoFileName = basename($photoSrc);
            $photoPath = dirname($photoSrc);

            $stubFolder = getValue($type, $this->folders);
            $trimFolder = combinePaths(array($this->sourceFolder, $stubFolder));
            $photoPath = str_replace($trimFolder, '', $photoPath);
            $photoFolder = combinePaths(array($this->targetFolder, $photoPath));
            @mkdir($photoFolder, 0777, true);

            if (!file_exists($photoSrc)) {
                $errors[] = "Missing file: {$photoSrc}";
                continue;
            }

            $typePrefix = getValue($type, $this->types);
            $photoDest = combinePaths(array($photoFolder, "{$typePrefix}{$photoFileName}"));
            $copied = @copy($photoSrc, $photoDest);
            if (!$copied) {
                $errors[] = "! failed to copy photo '{$photoSrc}' (-> {$photoDest}).";
            }

            if (!($this->processed % 100)) {
                echo " - processed {$this->processed}\n";
            }
        }
    }

    /*
     * Forum-specific export format.
     * @param ExportModel $ex
     */

    protected function forumExport($ex) {
        $this->ex = $ex;

        $cdn = $this->cdnPrefix();

        $characterSet = $ex->getCharacterSet('posts');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->sourcePrefix = 'xf_';
//      $ex->UseCompression(FALSE);
        // Begin
        $ex->beginExport('', 'xenforo', array('HashMethod' => 'xenforo'));

        // Export avatars
        if ($this->param('avatars')) {
            $this->doAvatars();
        }

        // Users.
        $user_Map = array(
            'user_id' => 'UserID',
            'username' => 'Name',
            'email' => 'Email',
            'gender' => array(
                'Column' => 'Gender',
                'Filter' => function ($value) {
                    switch ($value) {
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
        $ex->exportTable('User', "
         select
            u.*,
            ua.data as password,
            'xenforo' as hash_method,
            case when u.avatar_date > 0 then concat('{$cdn}xf/', u.user_id div 1000, '/', u.user_id, '.jpg') else null end as avatar
         from :_user u
         left join :_user_authenticate ua
            on u.user_id = ua.user_id", $user_Map);

        // Roles.
        $role_Map = array(
            'user_group_id' => 'RoleID',
            'title' => 'Name'
        );
        $ex->exportTable('Role', "
         select *
         from :_user_group", $role_Map);

        // User Roles.
        $userRole_Map = array(
            'user_id' => 'UserID',
            'user_group_id' => 'RoleID'
        );

        $ex->exportTable('UserRole', "
         select user_id, user_group_id
         from :_user

         union all

         select u.user_id, ua.user_group_id
         from :_user u
         join :_user_group ua
            on find_in_set(ua.user_group_id, u.secondary_group_ids)", $userRole_Map);

        // Permission.
        $this->exportPermissions();

        // User Meta.
        $this->exportUserMeta();

        // Categories.
        $category_Map = array(
            'node_id' => 'CategoryID',
            'title' => 'Name',
            'description' => 'Description',
            'parent_node_id' => array(
                'Column' => 'ParentCategoryID',
                'Filter' => function ($value) {
                    return $value ? $value : null;
                }
            ),
            'display_order' => 'Sort',
            'display_in_list' => array('Column' => 'HideAllDiscussions', 'Filter' => 'NotFilter')
        );
        $ex->exportTable('Category', "
         select n.*
         from :_node n
         ", $category_Map);

        // Discussions.
        $discussion_Map = array(
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
        $ex->exportTable('Discussion', "
         select
            t.*,
            p.message,
            'BBCode' as format,
            ip.ip
         from :_thread t
         join :_post p
            on t.first_post_id = p.post_id
         left join :_ip ip
            on p.ip_id = ip.ip_id", $discussion_Map);


        // Comments.
        $comment_Map = array(
            'post_id' => 'CommentID',
            'thread_id' => 'DiscussionID',
            'user_id' => 'InsertUserID',
            'post_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'message' => 'Body',
            'format' => 'Format',
            'ip' => array('Column' => 'InsertIPAddress', 'Filter' => 'long2ipf')
        );
        $ex->exportTable('Comment', "
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
            and message_state = 'visible'", $comment_Map);

        // Conversation.
        $conversation_Map = array(
            'conversation_id' => 'ConversationID',
            'title' => 'Subject',
            'user_id' => 'InsertUserID',
            'start_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate')
        );
        $ex->exportTable('Conversation', "
         select *
         from :_conversation_master", $conversation_Map);

        $conversationMessage_Map = array(
            'message_id' => 'MessageID',
            'conversation_id' => 'ConversationID',
            'message_date' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'user_id' => 'InsertUserID',
            'message' => 'Body',
            'format' => 'Format',
            'ip' => array('Column' => 'InsertIPAddress', 'Filter' => 'long2ipf')
        );
        $ex->exportTable('ConversationMessage', "
         select
            m.*,
            'BBCode' as format,
            ip.ip
         from :_conversation_message m
         left join :_ip ip
            on m.ip_id = ip.ip_id", $conversationMessage_Map);

        $userConversation_Map = array(
            'conversation_id' => 'ConversationID',
            'user_id' => 'UserID',
            'Deleted' => 'Deleted'
        );
        $ex->exportTable('UserConversation', "
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
         ", $userConversation_Map);

        $ex->endExport();
    }

    public function exportPermissions() {
        $ex = $this->ex;

        $permissions = array();

        // Export the global permissions.
        $r = $ex->query("
         select
            pe.*,
            g.title
         from :_permission_entry pe
         join :_user_group g
            on pe.user_group_id = g.user_group_id");
        $this->_exportPermissions($r, $permissions);

        $r = $ex->query("
          select
            pe.*,
            g.title
         from :_permission_entry_content pe
         join :_user_group g
            on pe.user_group_id = g.user_group_id");
        $this->_exportPermissions($r, $permissions);


        if (count($permissions) == 0) {
            return;
        }

        $permissions = array_values($permissions);

        // Now that we have all of the permission in an array let's export them.
        $columns = $this->_exportPermissions(false);

        foreach ($columns as $index => $column) {
            if (strpos($column, '.') !== false) {
                $columns[$index] = array('Column' => $column, 'Type' => 'tinyint');
            }
        }
        $structure = $ex->getExportStructure($columns, 'Permission', $columns, 'Permission');
        $revMappings = $ex->flipMappings($columns);

        $ex->writeBeginTable($ex->file, 'Permission', $structure);
        $count = 0;
        foreach ($permissions as $row) {
            $ex->writeRow($ex->file, $row, $structure, $revMappings);
            $count++;
        }
        $ex->writeEndTable($ex->file);
        $ex->comment("Exported Table: Permission ($count rows)");

//       var_export($permissions);
    }

    public function exportUserMeta() {
        $ex = $this->ex;

        $sql = "
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

        $ex->exportTable('UserMeta', $sql);
    }

    protected function _exportPermissions($r, &$perms = null) {
        $map = array(
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
            $result = array(
                'RoleID' => 'RoleID',
                'JunctionTable' => 'JunctionTable',
                'JunctionColumn' => 'JunctionColumn',
                'JunctionID' => 'JunctionID',
                '_Permissions' => '_Permissions',
                'Garden.Moderation.Manage' => 'Garden.Moderation.Manage'
            );

            // Return an array of fieldnames.
            foreach ($map as $columns) {
                $columns = (array)$columns;
                foreach ($columns as $column) {
                    $result[$column] = $column;
                }
            }

            return $result;
        }

        while ($row = mysql_fetch_assoc($r)) {
            $roleID = $row['user_group_id'];

            $perm = "{$row['permission_group_id']}.{$row['permission_id']}";

            if (!isset($map[$perm])) {
                continue;
            }

            $names = (array)$map[$perm];

            foreach ($names as $name) {
                if (isset($row['content_id'])) {
                    if ($row['content_type'] != 'node') {
                        continue;
                    }

                    $categoryID = $row['content_id'];
                } else {
                    $categoryID = null;
                }

                // Is this a per-category permission?
                if (strpos($name, 'Vanilla.Discussions.') !== false || strpos($name, 'Vanilla.Comments.') !== false) {
                    if (!$categoryID) {
                        $categoryID = -1;
                    }
                } else {
                    $categoryID = null;
                }


                $key = "{$roleID}_{$categoryID}";

                $perms[$key]['RoleID'] = $roleID;
                $permRow = &$perms[$key];
                if ($categoryID) {
                    $permRow['JunctionTable'] = 'Category';
                    $permRow['JunctionColumn'] = 'PermissionCategoryID';
                    $permRow['JunctionID'] = $categoryID;
                }

                $title = $row['title'];
                $permRow['Title'] = $title;
                if (stripos($title, 'Admin') !== false) {
                    $permRow['_Permissions'] = 'all';
                }
                if (!$categoryID && stripos($title, 'Mod') !== false) {
                    $permRow['Garden.Moderation.Manage'] = true;
                }

                // Set all of the permissions.
                $permValue = $row['permission_value'];
                if ($permValue == 'deny') {
                    $permRow[$name] = false;
                } elseif (in_array($permValue, array('allow', 'content_allow'))) {
                    if (!isset($permRow[$name]) || $permRow[$name] !== false) {
                        $permRow[$name] = true;
                    }
                } elseif (!isset($permRow[$name])) {
                    $permRow[$name] = null;
                }
            }
        }
    }

}

// Closing PHP tag required. (make.php)
?>
