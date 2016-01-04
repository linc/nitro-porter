<?php
/**
 * PunBB exporter tool
 *
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['punbb'] = array('name' => 'PunBB 1', 'prefix' => 'punbb_');
$supported['punbb']['CommandLine'] = array(
    'avatarpath' => array('Full path of forum avatars.', 'Sx' => '::')
);
$supported['punbb']['features'] = array(
    'Avatars' => 1,
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Attachments' => 1,
    'Permissions' => 1,
    'Tags' => 1,
    'Signatures' => 1,
    'Passwords' => 1
);

class PunBB extends ExportController {

    /** @var bool Path to avatar images */
    protected $avatarPath = false;

    /** @var string CDN path prefix */
    protected $cdn = '';

    /** @var array Required tables => columns */
    public $sourceTables = array();

    /**
     * Forum-specific export format
     *
     * @todo Project file size / export time and possibly break into multiple files
     *
     * @param ExportModel $ex
     *
     */
    protected function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('posts');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->beginExport('', 'PunBB 1.*', array('HashMethod' => 'punbb'));

        $this->cdn = $this->param('cdn', '');

        if ($avatarPath = $this->param('avatarpath', false)) {
            if (!$avatarPath = realpath($avatarPath)) {
                echo "Unable to access path to avatars: $avatarPath\n";
                exit(1);
            }

            $this->avatarPath = $avatarPath;
        }
        unset($avatarPath);

        // User.
        $user_Map = array(
            'AvatarID' => array('Column' => 'Photo', 'Filter' => array($this, 'getAvatarByID')),
            'id' => 'UserID',
            'username' => 'Name',
            'email' => 'Email',
            'timezone' => 'HourOffset',
            'registration_ip' => 'InsertIPAddress',
            'PasswordHash' => 'Password'
        );
        $ex->exportTable('User', "
         SELECT
             u.*, u.id AS AvatarID,
             concat(u.password, '$', u.salt) AS PasswordHash,
             from_unixtime(registered) AS DateInserted,
             from_unixtime(last_visit) AS DateLastActive
         FROM :_users u
         WHERE group_id <> 2", $user_Map);

        // Role.
        $role_Map = array(
            'g_id' => 'RoleID',
            'g_title' => 'Name'
        );
        $ex->exportTable('Role', "SELECT * FROM :_groups", $role_Map);

        // Permission.
        $permission_Map = array(
            'g_id' => 'RoleID',
            'g_modertor' => 'Garden.Moderation.Manage',
            'g_mod_edit_users' => 'Garden.Users.Edit',
            'g_mod_rename_users' => 'Garden.Users.Delete',
            'g_read_board' => 'Vanilla.Discussions.View',
            'g_view_users' => 'Garden.Profiles.View',
            'g_post_topics' => 'Vanilla.Discussions.Add',
            'g_post_replies' => 'Vanilla.Comments.Add',
            'g_pun_attachment_allow_download' => 'Plugins.Attachments.Download.Allow',
            'g_pun_attachment_allow_upload' => 'Plugins.Attachments.Upload.Allow',

        );
        $permission_Map = $ex->fixPermissionColumns($permission_Map);
        $ex->exportTable('Permission', "
      SELECT
         g.*,
         g_post_replies AS `Garden.SignIn.Allow`,
         g_mod_edit_users AS `Garden.Users.Add`,
         CASE WHEN g_title = 'Administrators' THEN 'All' ELSE NULL END AS _Permissions
      FROM :_groups g", $permission_Map);

        // UserRole.
        $userRole_Map = array(
            'id' => 'UserID',
            'group_id' => 'RoleID'
        );
        $ex->exportTable('UserRole',
            "SELECT
            CASE u.group_id WHEN 2 THEN 0 ELSE id END AS id,
            u.group_id
          FROM :_users u", $userRole_Map);

        // Signatures.
        $ex->exportTable('UserMeta', "
         SELECT
         id,
         'Plugin.Signatures.Sig' AS Name,
         signature
      FROM :_users u
      WHERE u.signature IS NOT NULL", array('id ' => 'UserID', 'signature' => 'Value'));


        // Category.
        $category_Map = array(
            'id' => 'CategoryID',
            'forum_name' => 'Name',
            'forum_desc' => 'Description',
            'disp_position' => 'Sort',
            'parent_id' => 'ParentCategoryID'
        );
        $ex->exportTable('Category', "
      SELECT
        id,
        forum_name,
        forum_desc,
        disp_position,
        cat_id * 1000 AS parent_id
      FROM :_forums f
      UNION

      SELECT
        id * 1000,
        cat_name,
        '',
        disp_position,
        NULL
      FROM :_categories", $category_Map);

        // Discussion.
        $discussion_Map = array(
            'id' => 'DiscussionID',
            'poster_id' => 'InsertUserID',
            'poster_ip' => 'InsertIPAddress',
            'closed' => 'Closed',
            'sticky' => 'Announce',
            'forum_id' => 'CategoryID',
            'subject' => 'Name',
            'message' => 'Body'

        );
        $ex->exportTable('Discussion', "
      SELECT t.*,
        from_unixtime(p.posted) AS DateInserted,
        p.poster_id,
        p.poster_ip,
        p.message,
        from_unixtime(p.edited) AS DateUpdated,
        eu.id AS UpdateUserID,
        'BBCode' AS Format
      FROM :_topics t
      LEFT JOIN :_posts p
        ON t.first_post_id = p.id
      LEFT JOIN :_users eu
        ON eu.username = p.edited_by", $discussion_Map);

        // Comment.
        $comment_Map = array(
            'id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'poster_id' => 'InsertUserID',
            'poster_ip' => 'InsertIPAddress',
            'message' => 'Body'
        );
        $ex->exportTable('Comment', "
            SELECT p.*,
        'BBCode' AS Format,
        from_unixtime(p.posted) AS DateInserted,
        from_unixtime(p.edited) AS DateUpdated,
        eu.id AS UpdateUserID
      FROM :_topics t
      JOIN :_posts p
        ON t.id = p.topic_id
      LEFT JOIN :_users eu
        ON eu.username = p.edited_by
      WHERE p.id <> t.first_post_id;", $comment_Map);

        if ($ex->exists('tags')) {
            // Tag.
            $tag_Map = array(
                'id' => 'TagID',
                'tag' => 'Name'
            );
            $ex->exportTable('Tag', "SELECT * FROM :_tags", $tag_Map);

            // TagDisucssion.
            $tagDiscussionMap = array(
                'topic_id' => 'DiscussionID',
                'tag_id' => 'TagID'
            );
            $ex->exportTable('TagDiscussion', "SELECT * FROM :_topic_tags", $tagDiscussionMap);
        }

        if ($ex->exists('attach_files')) {
            // Media.
            $media_Map = array(
                'id' => 'MediaID',
                'filename' => 'Name',
                'file_mime_type' => 'Type',
                'size' => 'Size',
                'owner_id' => 'InsertUserID',
                'thumb_path' => array('Column' => 'ThumbPath', 'Filter' => array($this, 'filterThumbnailData')),
                'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'filterThumbnailData')),
            );
            $ex->exportTable('Media', "
                select f.*,
                    concat({$this->cdn}, 'FileUpload/', f.file_path) as Path,
                    concat({$this->cdn}, 'FileUpload/', f.file_path) as thumb_path,
                    128 as thumb_width,
                    from_unixtime(f.uploaded_at) as DateInserted,
                    case when post_id is null then 'Discussion' else 'Comment' end as ForeignTable,
                    coalesce(post_id, topic_id) as ForieignID
                from :_attach_files f
            ", $media_Map);
        }

        // End
        $ex->endExport();
    }

    public function stripMediaPath($absPath) {
        if (($pos = strpos($absPath, '/uploads/')) !== false) {
            return substr($absPath, $pos + 9);
        }

        return $absPath;
    }

    public function filterPermissions($permissions, $columnName, &$row) {
        $permissions2 = unserialize($permissions);

        foreach ($permissions2 as $name => $value) {
            if (is_null($value)) {
                $permissions2[$name] = false;
            }
        }

        if (is_array($permissions2)) {
            $row = array_merge($row, $permissions2);
            $this->ex->currentRow = $row;

            return isset($permissions2['PERMISSION_ADD_COMMENTS']) ? $permissions2['PERMISSION_ADD_COMMENTS'] : false;
        }

        return false;
    }

    public function forceBool($value) {
        if ($value) {
            return true;
        }

        return false;
    }

    /**
     * Take the user ID, avatar type value and generate a path to the avatar file.
     *
     * @param $value Row field value.
     * @param $field Name of the current field.
     * @param $row All of the current row values.
     *
     * @return null|string
     */
    public function getAvatarByID($value, $field, $row) {
        if (!$this->avatarPath) {
            return null;
        }

        switch ($row['avatar']) {
            case 1:
                $extension = 'gif';
                break;
            case 2:
                $extension = 'jpg';
                break;
            case 3:
                $extension = 'png';
                break;
            default:
                return null;
        }

        $avatarFilename = "{$this->avatarPath}/{$value}.$extension";

        if (file_exists($avatarFilename)) {
            $avatarBasename = basename($avatarFilename);

            return "{$this->cdn}punbb/avatars/$avatarBasename";
        } else {
            return null;
        }
    }

    /**
     * Filter used by $Media_Map to replace value for ThumbPath and ThumbWidth when the file is not an image.
     *
     * @access public
     * @see ExportModel::_exportTable
     *
     * @param string $value Current value
     * @param string $field Current field
     * @param array $row Contents of the current record.
     * @return string|null Return the supplied value if the record's file is an image. Return null otherwise
     */
    public function filterThumbnailData($value, $field, $row) {
        if (strpos(strtolower($row['file_mime_type']), 'image/') === 0) {
            return $value;
        } else {
            return null;
        }
    }
}

// Closing PHP tag required. (make.php)
?>
