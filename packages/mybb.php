<?php
/**
 * MyBB exporter tool.
 *
 * @copyright Vanilla Forums Inc. 2010-2014
 * @license GNU GPL2
 * @package VanillaPorter
 * @see functions.commandline.php for command line usage.
 */

$Supported['mybb'] = array('name' => 'MyBB', 'prefix' => 'mybb_');
$Supported['mybb']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Passwords' => 1,
    'Avatars' => 1,
    'Bookmarks' => 1,
);

class MyBB extends ExportController {
    /**
     * You can use this to require certain tables and columns be present.
     *
     * @var array Required tables => columns
     */
    protected $SourceTables = array(
        'forums' => array(),
        'posts' => array(),
        'threads' => array(),
        'users' => array(),
    );

    /**
     * Main export process.
     *
     * @param ExportModel $Ex
     * @see $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($Ex) {

        $CharacterSet = $Ex->getCharacterSet('posts');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        // Reiterate the platform name here to be included in the porter file header.
        $Ex->beginExport('', 'MyBB');

        // User.
        $User_Map = array(
            'uid' => 'UserID',
            'username' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'avatar' => 'Photo',
            'regdate2' => 'DateInserted',
            'regdate3' => 'DateFirstVisit',
            'email' => 'Email',
        );
        $Ex->exportTable('User', "
         select u.*,
            FROM_UNIXTIME(regdate) as regdate2,
            FROM_UNIXTIME(regdate) as regdate3,
            FROM_UNIXTIME(lastactive) as DateLastActive,
            concat(password, salt) as Password,
            'mybb' as HashMethod
         from :_users u
         ", $User_Map);

        // Role.
        $Role_Map = array(
            'gid' => 'RoleID',
            'title' => 'Name',
            'description' => 'Description',
        );
        $Ex->exportTable('Role', "
         select *
         from :_usergroups", $Role_Map);

        // User Role.
        $UserRole_Map = array(
            'uid' => 'UserID',
            'usergroup' => 'RoleID',
        );
        $Ex->exportTable('UserRole', "
         select u.uid, u.usergroup
         from :_users u", $UserRole_Map);

        // Category.
        $Category_Map = array(
            'fid' => 'CategoryID',
            'pid' => 'ParentCategoryID',
            'disporder' => 'Sort',
            'name' => 'Name',
            'description' => 'Description',
        );
        $Ex->exportTable('Category', "
         select *
         from :_forums f
         ", $Category_Map);

        // Discussion.
        $Discussion_Map = array(
            'tid' => 'DiscussionID',
            'fid' => 'CategoryID',
            'uid' => 'InsertUserID',
            'subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'views' => 'CountViews',
            'replies' => 'CountComments',
        );
        $Ex->exportTable('Discussion', "
         select *,
            FROM_UNIXTIME(dateline) as DateInserted,
            'BBCode' as Format
         from :_threads t", $Discussion_Map);

        // Comment.
        $Comment_Map = array(
            'pid' => 'CommentID',
            'tid' => 'DiscussionID',
            'uid' => 'InsertUserID',
            'message' => array('Column' => 'Body'),
        );
        $Ex->exportTable('Comment', "
         select p.*,
            FROM_UNIXTIME(dateline) as DateInserted,
            'BBCode' as Format
         from :_posts p", $Comment_Map);

        // UserDiscussion.
        $UserDiscussion_Map = array(
            'tid' => 'DiscussionID',
            'uid' => 'UserID',
        );
        $Ex->exportTable('UserDiscussion', "
         select *,
            1 as Bookmarked
         from :_threadsubscriptions t", $UserDiscussion_Map);

        $Ex->endExport();
    }
}

?>
