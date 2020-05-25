<?php
/**
 * MVC exporter tool.
 *
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GNU GPL2
 * @package VanillaPorter
 * @see functions.commandline.php for command line usage.
 */

$supported['mvc'] = array('name' => 'mvc', 'prefix' => 'mvc_');
$supported['mvc']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Badge,' => 1,
    'Categories' => 1,
    'Roles' => 1,
);

class MVC extends ExportController {
    /**
     * You can use this to require certain tables and columns be present.
     *
     * @var array Required tables => columns
     */
    protected $sourceTables = array(
        'MembershipUser' => array(),
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
        $ex->beginExport('', 'MVC');

        // TODO Convert ID and map them

        // User.
        $user_Map = array();
        $ex->exportTable('User', "
            select
                u.Id as UserID,
                u.UserName as Name,
                'Reset' as HashMethod,
                u.Email as Email,
                u.Avatar as Photo,
                u.CreateDate as DateInserted,
                u.LastLoginDate as DateLastVisit,
                u.LastActivityDate as DateLastActive,
                u.IsBanned as Banned,
                u.Location as Location
            from :_MembershipUser as u
         ", $user_Map);

        // Role.
        $role_Map = array();
        $ex->exportTable('Role', "
            select
                r.Id as RoleID,
                r.RoleName as Name
            from :_MembershipRole as r
         ", $role_Map);

        // User Role.
        $userRole_Map = array();
        $ex->exportTable('UserRole', "
            select
                u.UserIdentifier as UserID,
                u.RoleIdentifier as RoleID
            from :_MembershipUsersInRoles as u
        ", $userRole_Map);

        //Badge
        $Badge_Map = array();
        $ex->exportTable('Badge', "
            select
                b.Id as BadgeID,
                b.Type as Type,
                b.DisplayName as Name,
                b.Description as Descripton,
                b.Image as Photo,
                b.AwardsPoints as Points
            from :_Badge as b
        ", $Badge_Map);

        // TODO Assign default parent category
        // Category.
        $category_Map = array();
        $ex->exportTable('Category', "
            select
                f.Id as CategoryID,
                f.Category_Id as ParentCategoryID,
                f.Name as Name,
                f.Description as Description,
                f.DateCreated as DateInserted,
                null as Sort
            from :_Category as f
        ", $category_Map);

        // TODO : handle FAWMechPost and FAQMechTopic
        // Discussion.
        $discussion_Map = array();
        $ex->exportTable('Discussion', "
            select
                t.Id as DiscussionID,
                t.Category_id as CategoryID,
                t.MembershipUser_id as InsertUserID,
                t.CreateDate as DateInserted,
                t.Name as Name,
                t.Views as CountViews,
                'BBCode' as Format
            from :_Topic as t
            ", $discussion_Map);

        // Comment.
        $comment_Map = array();
        $ex->exportTable('Comment', "
            select
                p.Id as CommentID,
                p.Topic_id as DiscussionID,
                p.MembershipUser_id as InsertUserID,
                p.PostContent as Body,
                p.DateCreated as DateInserted,
                p.DateEdited as DateUpdated,
                'BBCode' as Format
            from :_Post as p
         ", $comment_Map);

        // Global permissions

        $ex->endExport();
    }

    private function idConverter(){
        // TODO
    }
}

// Closing PHP tag required. (make.php)
?>
