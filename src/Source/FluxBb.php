<?php

/**
 * FluxBB exporter tool
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Francis Caisse
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class FluxBb extends Source
{
    public const SUPPORTED = [
        'name' => 'FluxBB 1',
        'prefix' => '',
        'charset_table' => 'posts',
        'hashmethod' => 'punbb', // FluxBB is a fork of punbb and the password works.
        'options' => [
            'avatars-source' => array(
                'Full path of forum avatars.',
                'Sx' => '::',
            )
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
     * @var bool Path to avatar images
     */
    protected $avatarPath = false;

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
        if ($this->avatarPath = $this->param('avatars-source')) {
            if (!$this->avatarPath = realpath($this->avatarPath)) {
                exit("Unable to access path to avatars: $this->avatarPath\n");
            }
        }

        $this->users($ex);
        $this->permissions($ex);
        $this->roles($ex);
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
     * @param $value Row field value.
     * @param $field Name of the current field.
     * @param $row   All of the current row values.
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
            return "{$this->cdn}fluxbb/img/avatars/$avatarBasename";
        } else {
            return null;
        }
    }

    /**
     * Filter used by $Media_Map to replace value for ThumbPath and ThumbWidth when the file is not an image.
     *
     * @access public
     * @see    ExportModel::exportTableWrite
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
    protected function users(ExportModel $ex): void
    {
        $user_Map = array(
            'AvatarID' => array('Column' => 'Photo', 'Filter' => array($this, 'getAvatarByID')),
        );
        $ex->exportTable(
            'User',
            "select
                    u.id as UserID,
                    u.username as UserID,
                    u.email as Email,
                    u.timezone as HourOffset,
                    u.registration_ip as InsertIPAddress,
                    u.id as AvatarID,
                    u.password as Password,
                    from_unixtime(u.registered) as DateInserted,
                    from_unixtime(u.last_visit) as DateLastActive
                from :_users u
                where group_id <> 2",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function permissions(ExportModel $ex): void
    {
        $ex->exportTable(
            'Permission',
            "select
                    g_id,
                    g_moderator as 'Garden.Moderation.Manage',
                    g_mod_edit_users as 'Garden.Users.Edit',
                    g_mod_rename_users as 'Garden.Users.Delete',
                    g_read_board as 'Vanilla.Discussions.View',
                    g_view_users as 'Garden.Profiles.View',
                    g_post_topics as 'Vanilla.Discussions.Add',
                    g_post_replies as 'Vanilla.Comments.Add',
                    g_post_replies as 'Garden.SignIn.Allow',
                    g_mod_edit_users as 'Garden.Users.Add',
                    case
                        when g_title = 'Administrators' then 'All' else NULL
                    end as _Permissions
                from :_groups"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $ex->exportTable(
            'Role',
            "select g_id as RoleID, g_title as Name from :_groups"
        );
        // UserRole
        $ex->exportTable(
            'UserRole',
            "select u.id as UserID, u.group_id as RoleID from :_users u"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function signatures(ExportModel $ex): void
    {
        $ex->exportTable(
            'UserMeta',
            "select
                    u.id as UserID,
                    'Plugin.Signatures.Sig' as Name,
                    signature as Value
                from :_users u
                where u.signature is not null"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $ex->exportTable(
            'Category',
            "select
                    id as CategoryID,
                    forum_name as Name,
                    forum_desc as Description,
                    disp_position as Sort,
                    cat_id * 1000 as ParentCategoryID
                from :_forums f
                union
                select
                    id * 1000 as CategoryID,
                    cat_name as Name,
                    '' as Description,
                    disp_position as Sort,
                    NULL as ParentCategoryID
                from :_categories"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $ex->exportTable(
            'Discussion',
            "select
                    t.id as DiscussionID,
                    from_unixtime(p.posted) as DateInserted,
                    p.poster_id as InsertUserID,
                    p.poster_ip as InsertIPAddress,
                    p.message as Body,
                    t.closed as Closed,
                    t.sticky as Announce,
                    t.forum_id as CategoryID,
                    t.subject as Name,
                    from_unixtime(p.edited) as DateUpdated,
                    u.id as UpdateUserID,
                    'BBCode' as Format
                from :_topics t
                left join :_posts p on t.first_post_id = p.id
                left join :_users u on u.username = p.edited_by"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $ex->exportTable(
            'Comment',
            "select
                    p.*,
                    p.id as CommentID,
                    p.poster_id as InsertUserID,
                    p.poster_ip as InsertIPAddress,
                    p.message as Body,
                    'BBCode' as Format,
                    from_unixtime(p.posted) as DateInserted,
                    from_unixtime(p.edited) as DateUpdated,
                    u.id as UpdateUserID
                from :_topics t
                join :_posts p on t.id = p.topic_id
                left join :_users u on u.username = p.edited_by
                where p.id <> t.first_post_id;"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function tags(ExportModel $ex): void
    {
        if ($ex->exists('tags')) {
            $ex->exportTable(
                'Tag',
                "select id as TagID, tag as Name from :_tags"
            );
            $ex->exportTable(
                'TagDiscussion',
                "select topic_id as DiscussionID, tag_id as TagID from :_topic_tags"
            );
        }
    }

    /**
     * @param ExportModel $ex
     */
    protected function attachments(ExportModel $ex): void
    {
        if ($ex->exists('attach_files')) {
            $media_Map = array(
                'owner_id' => 'InsertUserID',
                'thumb_path' => array('Column' => 'ThumbPath', 'Filter' => array($this, 'filterThumbnailData')),
                'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'filterThumbnailData')),
            );
            $ex->exportTable(
                'Media',
                "select f.*,
                        f.id as MediaID,
                        f.filename as Name,
                        f.size as Size,
                        f.type as Type,
                        f.ownder_id as InsertUserID,
                        concat({$this->cdn}, 'FileUpload/', f.file_path) as Path,
                        concat({$this->cdn}, 'FileUpload/', f.file_path) as thumb_path,
                        128 as thumb_width,
                        from_unixtime(f.uploaded_at) as DateInserted,
                        case when f.post_id is null then 'Discussion' else 'Comment' end as ForeignTable,
                        coalesce(f.post_id, f.topic_id) as ForeignID
                    from :_attach_files f",
                $media_Map
            );
        }
    }
}
