<?php

/**
 * Joomla Kunena exporter tool
 *
 * @author  Todd Burry
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class Kunena extends Source
{
    public const SUPPORTED = [
        'name' => 'Joomla Kunena',
        'prefix' => 'jos_',
        'charset_table' => 'kunena_messages',
        'hashmethod' => 'joomla',
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
            'Signatures' => 0,
            'Attachments' => 1,
            'Bookmarks' => 1,
        ]
    ];

    /**
     * @param ExportModel $ex
     */
    public function run($ex)
    {
        $this->users($ex);
        $this->roles($ex);
        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);
        $this->bookmarks($ex);
        $this->attachments($ex);
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
        if (strpos(strtolower($row['filetype']), 'image/') === 0) {
            return $value;
        }
        return null;
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $user_Map = array(
            'id' => 'UserID',
            'name' => 'Name',
            'email' => 'Email',
            'registerDate' => 'DateInserted',
            'lastvisitDate' => 'DateLastActive',
            'password' => 'Password',
            'showemail' => 'ShowEmail',
            'birthdate' => 'DateOfBirth',
            'banned' => 'Banned',
            //         'DELETED'=>'Deleted',
            'admin' => array('Column' => 'Admin', 'Type' => 'tinyint(1)'),
            'Photo' => 'Photo'
        );
        $ex->export(
            'User',
            "SELECT
                    u.*,
                    case when ku.avatar <> '' then concat('kunena/avatars/', ku.avatar) else null end as `Photo`,
                    case group_id when 'superadministrator' then 1 else 0 end as admin,
                    if(isnull(ku.banned), 0, 1) as banned,
                    ku.birthdate,
                    !ku.hideemail as showemail
                FROM :_users u
                left join :_kunena_users ku
                    on ku.userid = u.id",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $role_Map = array(
            'rank_id' => 'RoleID',
            'rank_title' => 'Name',
        );
        $ex->export('Role', "select * from :_kunena_ranks", $role_Map);

        // UserRole.
        $userRole_Map = array(
            'id' => 'UserID',
            'rank' => 'RoleID'
        );
        $ex->export(
            'UserRole',
            "select * from :_users u",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array(
            'id' => 'CategoryID',
            'parent' => 'ParentCategoryID',
            'name' => 'Name',
            'ordering' => 'Sort',
            'description' => 'Description',

        );
        $ex->export(
            'Category',
            "select * from :_kunena_categories",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
            'id' => 'DiscussionID',
            'catid' => 'CategoryID',
            'userid' => 'InsertUserID',
            'subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'time' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'ip' => 'InsertIPAddress',
            'locked' => 'Closed',
            'hits' => 'CountViews',
            'modified_by' => 'UpdateUserID',
            'modified_time' => array('Column' => 'DateUpdated', 'Filter' => 'timestampToDate'),
            'message' => 'Body',
            'Format' => 'Format'
        );
        $ex->export(
            'Discussion',
            "select t.*,
                    txt.message,
                    'BBCode' as Format
                 from :_kunena_messages t
                 left join :_kunena_messages_text txt
                    on t.id = txt.mesid
                 where t.thread = t.id",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $comment_Map = array(
            'id' => 'CommentID',
            'thread' => 'DiscussionID',
            'userid' => 'InsertUserID',
            'time' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
            'ip' => 'InsertIPAddress',
            'modified_by' => 'UpdateUserID',
            'modified_time' => array('Column' => 'DateUpdated', 'Filter' => 'timestampToDate'),
            'message' => 'Body',
            'Format' => 'Format'
        );
        $ex->export(
            'Comment',
            "select t.*,
                    txt.message,
                    'BBCode' as Format
                 from :_kunena_messages t
                 left join :_kunena_messages_text txt
                    on t.id = txt.mesid
                 where t.thread <> t.id",
            $comment_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function bookmarks(ExportModel $ex): void
    {
        $userDiscussion_Map = array(
            'thread' => 'DiscussionID',
            'userid' => 'UserID'
        );
        $ex->export(
            'UserDiscussion',
            "select t.*, 1 as Bookmarked from :_kunena_user_topics t",
            $userDiscussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function attachments(ExportModel $ex): void
    {
        $media_Map = array(
            'id' => 'MediaID',
            'mesid' => 'ForeignID',
            'userid' => 'InsertUserID',
            'size' => 'Size',
            'path2' => array('Column' => 'Path', 'Filter' => 'urlDecode'),
            'thumb_path' => array('Column' => 'ThumbPath', 'Filter' => array($this, 'filterThumbnailData')),
            'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'filterThumbnailData')),
            'filetype' => 'Type',
            'filename' => array('Column' => 'Name', 'Filter' => 'urlDecode'),
            'time' => array('Column' => 'DateInserted', 'Filter' => 'timestampToDate'),
        );
        $ex->export(
            'Media',
            "select a.*,
                    concat(a.folder, '/', a.filename) as path2,
                    case when m.id = m.thread then 'discussion' else 'comment' end as ForeignTable,
                    m.time,
                    concat(a.folder, '/', a.filename) as thumb_path,
                    128 as thumb_width
                 from :_kunena_attachments a
                 join :_kunena_messages m
                    on m.id = a.mesid",
            $media_Map
        );
    }
}
