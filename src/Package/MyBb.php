<?php

/**
 * MyBB exporter tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 *
 * @see functions.commandline.php for command line usage.
 */

namespace Porter\Package;

use Porter\ExportController;
use Porter\ExportModel;

class MyBb extends ExportController
{
    public const SUPPORTED = [
        'name' => 'MyBB',
        'prefix' => 'mybb_',
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
            'Permissions' => 0,
            'Badges' => 0,
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 0,
            'Reactions' => 0,
            'Articles' => 0,
        ]
    ];

    /**
     * You can use this to require certain tables and columns be present.
     *
     * @var array Required tables => columns
     */
    protected $sourceTables = array(
        'forums' => array(),
        'posts' => array(),
        'threads' => array(),
        'users' => array(),
    );

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see   $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex)
    {
        $ex->setCharacterSet('posts');


        // Reiterate the platform name here to be included in the porter file header.
        $ex->beginExport('', 'MyBB');

        $this->users($ex);

        $this->roles($ex);

        $this->categories($ex);

        $this->discussions($ex);

        $this->comments($ex);

        $this->attachments($ex);
        $this->bookmarks($ex);

        $ex->endExport();
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $user_Map = array(
            'uid' => 'UserID',
            'username' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'avatar' => 'Photo',
            'regdate2' => 'DateInserted',
            'regdate3' => 'DateFirstVisit',
            'email' => 'Email',
        );
        $ex->exportTable(
            'User',
            "
         select u.*,
            FROM_UNIXTIME(regdate) as regdate2,
            FROM_UNIXTIME(regdate) as regdate3,
            FROM_UNIXTIME(lastactive) as DateLastActive,
            concat(password, salt) as Password,
            'mybb' as HashMethod
         from :_users u
         ",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $role_Map = array(
            'gid' => 'RoleID',
            'title' => 'Name',
            'description' => 'Description',
        );
        $ex->exportTable(
            'Role',
            "
         select *
         from :_usergroups",
            $role_Map
        );

        // User Role.
        $userRole_Map = array(
            'uid' => 'UserID',
            'usergroup' => 'RoleID',
        );
        $ex->exportTable(
            'UserRole',
            "
         select u.uid, u.usergroup
         from :_users u",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array(
            'fid' => 'CategoryID',
            'pid' => 'ParentCategoryID',
            'disporder' => 'Sort',
            'name' => 'Name',
            'description' => 'Description',
        );
        $ex->exportTable(
            'Category',
            "
         select *
         from :_forums f
         ",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
            'tid' => 'DiscussionID',
            'fid' => 'CategoryID',
            'uid' => 'InsertUserID',
            'subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'views' => 'CountViews',
            'replies' => 'CountComments',
        );
        $ex->exportTable(
            'Discussion',
            "
         select *,
            FROM_UNIXTIME(dateline) as DateInserted,
            'BBCode' as Format
         from :_threads t",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $comment_Map = array(
            'pid' => 'CommentID',
            'tid' => 'DiscussionID',
            'uid' => 'InsertUserID',
            'message' => array('Column' => 'Body'),
        );
        $ex->exportTable(
            'Comment',
            "
         select p.*,
            FROM_UNIXTIME(dateline) as DateInserted,
            'BBCode' as Format
         from :_posts p",
            $comment_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function attachments(ExportModel $ex): void
    {
        $media_Map = array(
            'aid' => 'MediaID',
            'pid' => 'ForeignID',
            'uid' => 'InsertUserId',
            'filesize' => 'Size',
            'filename' => 'Name',
            'height' => 'ImageHeight',
            'width' => 'ImageWidth',
            'filetype' => 'Type',
            'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'filterThumbnailData')),
        );
        $ex->exportTable(
            'Media',
            "
            select a.*,
                600 as thumb_width,
                concat('attachments/', a.thumbnail) as ThumbPath,
                concat('attachments/', a.attachname) as Path,
                'Comment' as ForeignTable
            from :_attachments a
            where a.pid > 0
        ",
            $media_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function bookmarks(ExportModel $ex): void
    {
        $userDiscussion_Map = array(
            'tid' => 'DiscussionID',
            'uid' => 'UserID',
        );
        $ex->exportTable(
            'UserDiscussion',
            "
         select *,
            1 as Bookmarked
         from :_threadsubscriptions t",
            $userDiscussion_Map
        );
    }
}
