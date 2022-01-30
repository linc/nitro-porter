<?php

/**
 * Xenforo exporter tool.
 *
 * To export avatars, provide ?avatars=1&folder=/path/to/avatars
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Package;

use Porter\ExportController;

class Xenforo extends ExportController
{
    public const SUPPORTED = [
        'name' => 'Xenforo',
        'prefix' => 'xf_',
        'options' => [
            'avatars-source' => [
                'Full path of source avatars to process.',
                'Sx' => ':',
            ],
            'attach-source' => [
                'Full path of source attachments to process.',
                'Sx' => ':',
            ],
            'attach-rename' => [
                'Whether to rename the attachment files.',
                'Sx' => ':',
            ],
        ],
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 1,
            'PrivateMessages' => 1,
            'Signatures' => 1,
            'Attachments' => 1,
            'Bookmarks' => 0,
            'Permissions' => 1,
            'Badges' => 0,
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 0,
            'Reactions' => 0,
            'Articles' => 0,
        ]
    ];

    protected $processed;
    protected $sourceFolder;
    protected $targetFolder;
    protected $folders;
    protected $types;

    /**
     * Export attachments into vanilla-compatibles names
     */
    public function attachmentFiles($ex)
    {
        // Check source folder
        $this->sourceFolder = $this->param('attach-source');
        if (!is_dir($this->sourceFolder)) {
            trigger_error("Source attachment folder '{$this->sourceFolder}' does not exist.");
        }

        // Set up a target folder
        $this->targetFolder = combinePaths(array($this->sourceFolder, 'xf_attachments'));
        if (!is_dir($this->targetFolder)) {
            @$made = mkdir($this->targetFolder, 0777, true);
            if (!$made) {
                trigger_error("Target attachment folder '{$this->targetFolder}' could not be created.");
            }
        }

        $r = $ex->query(
            "
            select
                ad.data_id,
                ad.filename,
                ad.file_hash
            from
                :_attachment a
            join
                :_attachment_data ad on a.data_id = ad.data_id
            where
                a.content_type = 'post'
        "
        );

        $found = 0;
        while ($row = $r->nextResultRow()) {
            $dataId = $row['data_id'];
            $filename = $row['filename'];
            $filehash = $row['file_hash'];

            $oldname = $dataId . '-' . $filehash . '.data';

            if (file_exists($this->sourceFolder . $oldname)) {
                $found++;
                copy($this->sourceFolder . $oldname, $this->targetFolder
                     . '/' . $dataId . '-' . str_replace(' ', '_', $filename));
            }
        }

        $ex->comment("Attachments: " . $found . " attachment(s) were found and converted during the process.");
    }

    /**
     * Export avatars into vanilla-compatibles names
     */
    public function avatars()
    {

        // Check source folder
        $this->sourceFolder = $this->param('avatars-source');
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

    protected function avatarFolder($folder, $type, &$errors)
    {
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

    protected function forumExport($ex)
    {
        $cdn = $this->cdnPrefix();

        $ex->setCharacterSet('posts');


        $ex->sourcePrefix = 'xf_';
        //      $ex->UseCompression(FALSE);
        // Begin
        $ex->beginExport('', 'xenforo', array('HashMethod' => 'xenforo'));

        // Export avatars
        if ($this->param('avatars')) {
            $this->avatars();
        }

        // Export attachments
        if ($this->param('attach-rename')) {
            $this->attachmentFiles($ex);
        }

        $this->users($ex, $cdn);

        $this->roles($ex);
        $this->permissions($ex);
        $this->userMeta($ex);
        $this->categories($ex);

        $this->discussions($ex);

        $this->comments($ex);

        $this->attachments($ex);
        $this->conversations($ex);

        $ex->endExport();
    }

    public function permissions($ex)
    {
        $permissions = array();

        // Export the global permissions.
        $r = $ex->query(
            "
         select
            pe.*,
            g.title
         from :_permission_entry pe
         join :_user_group g
            on pe.user_group_id = g.user_group_id"
        );
        $this->exportPermissionsMap($r, $permissions);

        $r = $ex->query(
            "
          select
            pe.*,
            g.title
         from :_permission_entry_content pe
         join :_user_group g
            on pe.user_group_id = g.user_group_id"
        );
        $this->exportPermissionsMap($r, $permissions);


        if (count($permissions) == 0) {
            return;
        }

        $permissions = array_values($permissions);

        // Now that we have all of the permission in an array let's export them.
        $columns = $this->exportPermissionsMap(false);

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

    public function userMeta($ex)
    {
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

    protected function exportPermissionsMap($r, &$perms = null)
    {
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

        while ($row = $r->nextResultRow()) {
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

    /**
     * @param $ex
     * @param string $cdn
     */
    protected function users($ex, string $cdn): void
    {
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
            'register_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'last_activity' => array('Column' => 'DateLastActive', 'Filter' => 'timestampToDate'),
            'is_admin' => 'Admin',
            'is_banned' => 'Banned',
            'password' => 'Password',
            'hash_method' => 'HashMethod',
            'avatar' => 'Photo'
        );
        $ex->exportTable(
            'User',
            "
         select
            u.*,
            ua.data as password,
            'xenforo' as hash_method,
            case when u.avatar_date > 0 then concat('{$cdn}xf/', u.user_id div 1000, '/', u.user_id, '.jpg')
                else null end as avatar
         from :_user u
         left join :_user_authenticate ua
            on u.user_id = ua.user_id",
            $user_Map
        );
    }

    /**
     * @param $ex
     */
    protected function roles($ex): void
    {
        $role_Map = array(
            'user_group_id' => 'RoleID',
            'title' => 'Name'
        );
        $ex->exportTable(
            'Role',
            "
         select *
         from :_user_group",
            $role_Map
        );

        // User Roles.
        $userRole_Map = array(
            'user_id' => 'UserID',
            'user_group_id' => 'RoleID'
        );

        $ex->exportTable(
            'UserRole',
            "
         select user_id, user_group_id
         from :_user
         union all
         select u.user_id, ua.user_group_id
         from :_user u
         join :_user_group ua
            on find_in_set(ua.user_group_id, u.secondary_group_ids)",
            $userRole_Map
        );
    }

    /**
     * @param $ex
     */
    protected function categories($ex): void
    {
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
        $ex->exportTable(
            'Category',
            "
         select n.*
         from :_node n
         ",
            $category_Map
        );
    }

    /**
     * @param $ex
     */
    protected function discussions($ex): void
    {
        $discussion_Map = array(
            'thread_id' => 'DiscussionID',
            'node_id' => 'CategoryID',
            'title' => 'Name',
            'view_count' => 'CountViews',
            'user_id' => 'InsertUserID',
            'post_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'sticky' => 'Announce',
            'discussion_open' => array('Column' => 'Closed', 'Filter' => 'NotFilter'),
            'last_post_date' => array('Column' => 'DateLastComment', 'Filter' => 'timestampToDate'),
            'message' => 'Body',
            'format' => 'Format',
            'ip' => array('Column' => 'InsertIPAddress', 'Filter' => 'long2ipf')
        );
        $ex->exportTable(
            'Discussion',
            "
         select
            t.*,
            p.message,
            'BBCode' as format,
            ip.ip
         from :_thread t
         join :_post p
            on t.first_post_id = p.post_id
         left join :_ip ip
            on p.ip_id = ip.ip_id",
            $discussion_Map
        );
    }

    /**
     * @param $ex
     */
    protected function comments($ex): void
    {
        $comment_Map = array(
            'post_id' => 'CommentID',
            'thread_id' => 'DiscussionID',
            'user_id' => 'InsertUserID',
            'post_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'message' => 'Body',
            'format' => 'Format',
            'ip' => array('Column' => 'InsertIPAddress', 'Filter' => 'long2ipf')
        );
        $ex->exportTable(
            'Comment',
            "
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
            and message_state = 'visible'",
            $comment_Map
        );
    }

    /**
     * @param $ex
     */
    protected function attachments($ex): void
    {
        $ex->exportTable(
            'Media',
            "
            select
                a.attachment_id as MediaID,
                ad.filename as Name,
                concat('xf_attachments/', a.data_id, '-', replace(ad.filename, ' ', '_')) as Path,
                ad.file_size as Size,
                ad.user_id as InsertUserID,
                from_unixtime(ad.upload_date) as DateInserted,
                p.ForeignID,
                p.ForeignTable,
                ad.width as ImageWidth,
                ad.height as ImageHeight,
                concat('xf_attachments/', a.data_id, '-', replace(ad.filename, ' ', '_')) as ThumbPath
            from
                xf_attachment a
            join
                (select
                    p.post_id,
                    if(p.post_id = t.first_post_id,t.thread_id, p.post_id)  as ForeignID,
                    if(p.post_id = t.first_post_id, 'discussion', 'comment') as ForeignTable
                from xf_post p
                join xf_thread t on t.thread_id = p.thread_id
                where p.message_state <> 'deleted'
                ) p on p.post_id = a.content_id
            join
                xf_attachment_data ad on ad.data_id = a.data_id
            where
                a.content_type = 'post'
        "
        );
    }

    /**
     * @param $ex
     */
    protected function conversations($ex): void
    {
        $conversation_Map = array(
            'conversation_id' => 'ConversationID',
            'title' => 'Subject',
            'user_id' => 'InsertUserID',
            'start_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate')
        );
        $ex->exportTable(
            'Conversation',
            "
         select *
         from :_conversation_master",
            $conversation_Map
        );

        $conversationMessage_Map = array(
            'message_id' => 'MessageID',
            'conversation_id' => 'ConversationID',
            'message_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'user_id' => 'InsertUserID',
            'message' => 'Body',
            'format' => 'Format',
            'ip' => array('Column' => 'InsertIPAddress', 'Filter' => 'long2ipf')
        );
        $ex->exportTable(
            'ConversationMessage',
            "
         select
            m.*,
            'BBCode' as format,
            ip.ip
         from :_conversation_message m
         left join :_ip ip
            on m.ip_id = ip.ip_id",
            $conversationMessage_Map
        );

        $userConversation_Map = array(
            'conversation_id' => 'ConversationID',
            'user_id' => 'UserID',
            'Deleted' => 'Deleted'
        );
        $ex->exportTable(
            'UserConversation',
            "
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
         ",
            $userConversation_Map
        );
    }
}
