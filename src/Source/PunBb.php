<?php

/**
 * PunBB exporter tool
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Todd Burry
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class PunBb extends Source
{
    public const SUPPORTED = [
        'name' => 'PunBB 1',
        'prefix' => 'punbb_',
        'charset_table' => 'posts',
        'hashmethod' => 'punbb',
        'options' => [
            'avatars-source' => [
                'Full path of forum avatars.',
                'Sx' => '::'
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
            'PrivateMessages' => 0,
            'Signatures' => 1,
            'Attachments' => 1,
            'Bookmarks' => 0,
            'Permissions' => 1,
            'Badges' => 0,
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 1,
        ]
    ];

    /**
     * @var string Path to avatar images
     */
    protected $avatarPath = '';

    /**
     * @var string CDN path prefix
     */
    protected $cdn = '';

    /**
     * @var array Required tables => columns
     */
    public $sourceTables = array();

    /**
     * Forum-specific export format
     *
     * @param ExportModel $ex
     *@todo Project file size / export time and possibly break into multiple files
     *
     */
    public function run($ex)
    {
        $this->cdn = $this->param('cdn', '');
        if ($avatarPath = $this->param('avatars-source', false)) {
            if (!$avatarPath = realpath($avatarPath)) {
                echo "Unable to access path to avatars: $avatarPath\n";
                exit(1);
            }
            $this->avatarPath = $avatarPath;
        }

        $this->users($ex);
        $this->roles($ex);
        $this->permissions($ex);
        $this->signatures($ex);

        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);
        $this->tags($ex);
        $this->attachments($ex);
    }

    /**
     * Take the user ID, avatar type value and generate a path to the avatar file.
     *
     * @param mixed $value Row field value.
     * @param string $field Name of the current field.
     * @param array $row All of the current row values.
     *
     * @return null|string
     */
    public function getAvatarByID($value, $field, $row)
    {
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
     * @see    ExportModel::writeTableToFile
     *
     * @param  string $value Current value
     * @param  string $field Current field
     * @param  array  $row   Contents of the current record.
     * @return string|null Return the supplied value if the record's file is an image. Return null otherwise
     */
    public function filterThumbnailData($value, $field, $row)
    {
        if (strpos(strtolower($row['file_mime_type']), 'image/') === 0) {
            return $value;
        } else {
            return null;
        }
    }

    /**
     * @param ExportModel $ex
     */
    protected function attachments(ExportModel $ex): void
    {
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
            $ex->export(
                'Media',
                "select f.*,
                        concat({$this->cdn}, 'FileUpload/', f.file_path) as Path,
                        concat({$this->cdn}, 'FileUpload/', f.file_path) as thumb_path,
                        128 as thumb_width,
                        from_unixtime(f.uploaded_at) as DateInserted,
                        case when post_id is null then 'Discussion' else 'Comment' end as ForeignTable,
                        coalesce(post_id, topic_id) as ForieignID
                    from :_attach_files f",
                $media_Map
            );
        }
    }

    /**
     * @param ExportModel $ex
     */
    protected function tags(ExportModel $ex): void
    {
        if ($ex->exists('tags')) {
            $tag_Map = array(
                'id' => 'TagID',
                'tag' => 'Name'
            );
            $ex->export('Tag', "SELECT * FROM :_tags", $tag_Map);

            $tagDiscussionMap = array(
                'topic_id' => 'DiscussionID',
                'tag_id' => 'TagID'
            );
            $ex->export('TagDiscussion', "SELECT * FROM :_topic_tags", $tagDiscussionMap);
        }
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $comment_Map = array(
            'id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'poster_id' => 'InsertUserID',
            'poster_ip' => 'InsertIPAddress',
            'message' => 'Body'
        );
        $ex->export(
            'Comment',
            "SELECT p.*,
                    'BBCode' AS Format,
                    from_unixtime(p.posted) AS DateInserted,
                    from_unixtime(p.edited) AS DateUpdated,
                    eu.id AS UpdateUserID
                FROM :_topics t
                JOIN :_posts p
                    ON t.id = p.topic_id
                LEFT JOIN :_users eu
                    ON eu.username = p.edited_by
                WHERE p.id <> t.first_post_id;",
            $comment_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
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
        $ex->export(
            'Discussion',
            "SELECT t.*,
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
                    ON eu.username = p.edited_by",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array(
            'id' => 'CategoryID',
            'forum_name' => 'Name',
            'forum_desc' => 'Description',
            'disp_position' => 'Sort',
            'parent_id' => 'ParentCategoryID'
        );
        $ex->export(
            'Category',
            "SELECT
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
            FROM :_categories",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function signatures(ExportModel $ex): void
    {
        $ex->export(
            'UserMeta',
            "select
                   u.id as UserID,
                   'Plugin.Signatures.Format' AS Name,
                   'BBCode' as Value
                from :_users u
                where u.signature is not null
                and u.signature != ''
                union
                select
                    u.id as UserID,
                    'Plugin.Signatures.Sig' AS Name,
                    signature as Value
                from :_users u
                where u.signature is not null
                and u.signature !=''"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $role_Map = array(
            'g_id' => 'RoleID',
            'g_title' => 'Name'
        );
        $ex->export('Role', "SELECT * FROM :_groups", $role_Map);

        // UserRole.
        $userRole_Map = array(
            'id' => 'UserID',
            'group_id' => 'RoleID'
        );
        $ex->export(
            'UserRole',
            "SELECT
                    CASE u.group_id WHEN 2 THEN 0 ELSE id END AS id,
                    u.group_id
                FROM :_users u",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function permissions(ExportModel $ex): void
    {
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
        $ex->export(
            'Permission',
            "SELECT
                    g.*,
                    g_post_replies AS `Garden.SignIn.Allow`,
                    g_mod_edit_users AS `Garden.Users.Add`,
                    CASE WHEN g_title = 'Administrators' THEN 'All' ELSE NULL END AS _Permissions
                FROM :_groups g",
            $permission_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $user_Map = array(
            'AvatarID' => array('Column' => 'Photo', 'Filter' => array($this, 'getAvatarByID')),
            'id' => 'UserID',
            'username' => 'Name',
            'email' => 'Email',
            'timezone' => 'HourOffset',
            'registration_ip' => 'InsertIPAddress',
            'PasswordHash' => 'Password'
        );
        $ex->export(
            'User',
            "SELECT
                     u.*, u.id AS AvatarID,
                     concat(u.password, '$', u.salt) AS PasswordHash,
                     from_unixtime(registered) AS DateInserted,
                     from_unixtime(last_visit) AS DateLastActive
                FROM :_users u
                WHERE group_id <> 2",
            $user_Map
        );
    }
}
