<?php
/**
 * MyBB exporter tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author Lincoln Russell, lincolnwebs.com
 *
 * @see functions.commandline.php for command line usage.
 */

$supported['mybb'] = array('name' => 'MyBB', 'prefix' => 'mybb_');
$supported['mybb']['features'] = array(
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
);

class MyBB extends ExportController {
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
     * @see $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('posts');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Reiterate the platform name here to be included in the porter file header.
        $ex->beginExport('', 'MyBB');

        // User.
        $user_Map = array(
            'uid' => 'UserID',
            'username' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'avatar' => 'Photo',
            'regdate2' => 'DateInserted',
            'regdate3' => 'DateFirstVisit',
            'email' => 'Email',
        );
        $ex->exportTable('User', "
         select u.*,
            FROM_UNIXTIME(regdate) as regdate2,
            FROM_UNIXTIME(regdate) as regdate3,
            FROM_UNIXTIME(lastactive) as DateLastActive,
            concat(password, salt) as Password,
            'mybb' as HashMethod
         from :_users u
         ", $user_Map);

        // Role.
        $role_Map = array(
            'gid' => 'RoleID',
            'title' => 'Name',
            'description' => 'Description',
        );
        $ex->exportTable('Role', "
         select *
         from :_usergroups", $role_Map);

        // User Role.
        $userRole_Map = array(
            'uid' => 'UserID',
            'usergroup' => 'RoleID',
        );
        $ex->exportTable('UserRole', "
         select u.uid, u.usergroup
         from :_users u", $userRole_Map);

        // Category.
        $category_Map = array(
            'fid' => 'CategoryID',
            'pid' => 'ParentCategoryID',
            'disporder' => 'Sort',
            'name' => 'Name',
            'description' => 'Description',
        );
        $ex->exportTable('Category', "
         select *
         from :_forums f
         ", $category_Map);

        // Discussion.
        $discussion_Map = array(
            'tid' => 'DiscussionID',
            'fid' => 'CategoryID',
            'uid' => 'InsertUserID',
            'subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'views' => 'CountViews',
            'replies' => 'CountComments',
        );
        $ex->exportTable('Discussion', "
         select *,
            FROM_UNIXTIME(dateline) as DateInserted,
            'BBCode' as Format
         from :_threads t", $discussion_Map);

        // Comment.
        $comment_Map = array(
            'pid' => 'CommentID',
            'tid' => 'DiscussionID',
            'uid' => 'InsertUserID',
            'message' => array('Column' => 'Body'),
        );
        $ex->exportTable('Comment', "
         select p.*,
            FROM_UNIXTIME(dateline) as DateInserted,
            'BBCode' as Format
         from :_posts p", $comment_Map);

        // Media
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
        $ex->exportTable('Media', "
            select a.*,
                600 as thumb_width,
                concat('attachments/', a.thumbnail) as ThumbPath,
                concat('attachments/', a.attachname) as Path,
                'Comment' as ForeignTable
            from :_attachments a
            where a.pid > 0
        ", $media_Map);

        // UserDiscussion.
        $userDiscussion_Map = array(
            'tid' => 'DiscussionID',
            'uid' => 'UserID',
        );
        $ex->exportTable('UserDiscussion', "
         select *,
            1 as Bookmarked
         from :_threadsubscriptions t", $userDiscussion_Map);

        $ex->endExport();
    }
}

// Closing PHP tag required. (make.php)
?>
