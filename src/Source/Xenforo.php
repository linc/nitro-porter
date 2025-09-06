<?php

/**
 * Xenforo exporter tool.
 *
 * To export avatars, provide ?avatars=1&folder=/path/to/avatars
 *
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class Xenforo extends Source
{
    public const SUPPORTED = [
        'name' => 'Xenforo',
        'prefix' => 'xf_',
        'charset_table' => 'posts',
        'hashmethod' => 'xenforo',
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
        $this->sourceFolder = ''; //$this->param('attach-source');
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
            "select
                ad.data_id,
                ad.filename,
                ad.file_hash
            from
                :_attachment a
            join
                :_attachment_data ad on a.data_id = ad.data_id
            where
                a.content_type = 'post'"
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
        $this->sourceFolder = ''; //$this->param('avatars-source');
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

    /**
     * @param string $folder
     * @param string $type
     * @param array $errors
     * @return void
     */
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

    /**
     * Forum-specific export format.
     *
     * @param ExportModel $ex
     */
    public function run(ExportModel $ex)
    {
        // Export avatars
        // $this->avatars();
        // Export attachments
        // $this->attachmentFiles($ex);

        $this->users($ex);
        $this->roles($ex);
        $this->userMeta($ex);

        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);
        $this->attachments($ex);
        $this->conversations($ex);
    }

    /**
     * @param ExportModel $ex
     */
    public function userMeta(ExportModel $ex)
    {
        $sql = "select
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
        $ex->export('UserMeta', $sql);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
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
        $ex->export(
            'User',
            "select u.*,
                    ua.data as password,
                    'xenforo' as hash_method,
                    case when u.avatar_date > 0 then concat('xf/', u.user_id div 1000, '/', u.user_id, '.jpg')
                        else null end as avatar
                from :_user u
                left join :_user_authenticate ua
                    on u.user_id = ua.user_id",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $role_Map = array(
            'user_group_id' => 'RoleID',
            'title' => 'Name'
        );
        $ex->export(
            'Role',
            "select * from :_user_group",
            $role_Map
        );

        // User Roles.
        $userRole_Map = array(
            'user_id' => 'UserID',
            'user_group_id' => 'RoleID'
        );

        $ex->export(
            'UserRole',
            "select user_id, user_group_id
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
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
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
        $ex->export(
            'Category',
            "select n.* from :_node n",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
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
        $ex->export(
            'Discussion',
            "select t.*,
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
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
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
        $ex->export(
            'Comment',
            "select p.*,
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
     * @param ExportModel $ex
     */
    protected function attachments(ExportModel $ex): void
    {
        $ex->export(
            'Media',
            "select
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
                from xf_attachment a
                join (select
                        p.post_id,
                        if(p.post_id = t.first_post_id,t.thread_id, p.post_id)  as ForeignID,
                        if(p.post_id = t.first_post_id, 'discussion', 'comment') as ForeignTable
                    from xf_post p
                    join xf_thread t on t.thread_id = p.thread_id
                    where p.message_state <> 'deleted'
                    ) p on p.post_id = a.content_id
                join xf_attachment_data ad on ad.data_id = a.data_id
                where a.content_type = 'post'"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function conversations(ExportModel $ex): void
    {
        $conversation_Map = array(
            'conversation_id' => 'ConversationID',
            'title' => 'Subject',
            'user_id' => 'InsertUserID',
            'start_date' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate')
        );
        $ex->export(
            'Conversation',
            "select *, substring(title, 1, 200) as title from :_conversation_master",
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
        $ex->export(
            'ConversationMessage',
            "select m.*,
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
        $ex->export(
            'UserConversation',
            "select
                    r.conversation_id,
                    user_id,
                    case when r.recipient_state = 'deleted' then 1 else 0 end as Deleted
                from :_conversation_recipient r
                union all
                select
                    cu.conversation_id,
                    cu.owner_user_id,
                    0
                from :_conversation_user cu",
            $userConversation_Map
        );
    }
}
