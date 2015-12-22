<?php
/**
 * BBPress 2 exporter tool
 *
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
$supported['bbpress2'] = array('name'=>'bbPress 2', 'prefix' => 'wp_');
$supported['bbpress2']['features'] = array(
    'Comments'        => 1,
    'Discussions'     => 1,
    'Users'           => 1,
    'Categories'      => 1,
    'Roles'           => 1,
    'Passwords'       => 1,
);

class BBPress2 extends ExportController {
    /** @var array Required tables => columns */
    protected $sourceTables = array(
        'postmeta' => array(),
        'posts' => array(),
        'usermeta' => array(),
        'users' => array('ID', 'user_login', 'user_pass', 'user_email', 'user_registered'),
    );
    /**
     * Forum-specific export format.
     * @param ExportModel $Ex
     */
    protected function forumExport($ex) {
        // Begin
        $ex->beginExport('', 'bbPress 2.*', array('HashMethod' => 'Vanilla'));

        // Users
        $ex->query("drop table if exists z_user;");
        $ex->query("
            create table `z_user` (
                `ID` bigint(20) unsigned not null AUTO_INCREMENT,
                `user_login` varchar(60) NOT NULL DEFAULT '',
                `user_pass` varchar(255) NOT NULL DEFAULT '',
                `hash_method` varchar(10) DEFAULT NULL,
                `user_email` varchar(100) NOT NULL DEFAULT '',
                `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                primary key (`ID`),
                KEY `user_email` (`user_email`)
            )
        ;");

        $userQuery = "
            select
                ID,
                user_login,
                user_pass,
                'Vanilla' AS hash_method,
                user_email,
                user_registered
            from :_users
        ";

        $ex->query("insert into z_user $userQuery");

        $guestUserQuery = "
            select
                user_login,
                'JL2AC3ORF2ZHDU00Z8V0Z1LFC58TY6NWA6IC5M1MIGGDCHNE7K' AS user_pass,
                'Random' AS hash_method,
                user_email,
                user_registered
            from (
                select
                    max(if(pm.meta_key = \"_bbp_anonymous_name\", pm.meta_value, null)) as user_login,
                    max(if(pm.meta_key = \"_bbp_anonymous_email\", pm.meta_value, null)) as user_email,
                    p.post_date as user_registered
                from :_posts as p
                    inner join :_postmeta as pm on pm.post_id = p.ID
                where  p.post_author = 0
                    and pm.meta_key in ('_bbp_anonymous_name', '_bbp_anonymous_email')
                group by
                    pm.post_id
            ) as u
            where user_email not in (select user_email from z_user group by user_email)
            group by user_email
        ";

        $ex->query("
            insert into z_user(
                /* ID auto_increment yay! */
                user_login,
                user_pass,
                hash_method,
                user_email,
                user_registered
            ) $guestUserQuery");

        $user_Map = array(
            'ID'=>'UserID',
            'user_login'=>'Name',
            'user_pass'=>'Password',
            'hash_method'=>'HashMethod',
            'user_email'=>'Email',
            'user_registered'=>'DateInserted',
        );
        $ex->exportTable('User', "select * from z_user;", $user_Map);

        // Roles
        $ex->exportTable('Role', "
            select
                1 as RoleID,
                'Guest' as Name
            union select 2, 'Administrator'
            union select 3, 'Moderator'
            union select 4, 'Member'
            union select 5, 'Blocked'
        ;");

        // UserRoles
        $userRole_Map = array(
            'user_id'=>'UserID'
        );
        $ex->exportTable('UserRole', "
            select
                distinct(user_id) as user_id,
                case
                    when locate('bbp_keymaster', meta_value) != 0 then 2
                    when locate('bbp_moderator', meta_value) != 0 then 3
                    when locate('bbp_participant', meta_value) != 0 then 4
                    when locate('bbp_blocked', meta_value) != 0 then 5
                    else 1 /* should be bbp_spectator or non-handled roles if that's even possible */
                end as RoleID
            from :_usermeta
            where meta_key = 'wp_capabilities'

            union all

            select
                ID as user_id,
                1 as RoleID
            from z_user
            where hash_method = 'Random'
        ;", $userRole_Map);

        // Categories
        $category_Map = array(
            'ID'=>'CategoryID',
            'post_title'=>'Name',
            'post_content'=>'Description',
            'post_name'=>'UrlCode',
            'menu_order'=>'Sort',
        );
        $ex->exportTable('Category', "
            select
                *,
                lower(post_name) as forum_slug,
                nullif(post_parent, 0) as ParentCategoryID
            from :_posts
            where post_type = 'forum'
        ;", $category_Map);

        // Discussions
        $discussion_Map = array(
            'ID'=>'DiscussionID',
            'post_parent'=>'CategoryID',
            'post_author'=>'InsertUserID',
            'post_title'=>'Name',
            'Format'=>'Format',
            'post_date'=>'DateInserted',
            'menu_order'=>'Announce',
        );
        $ex->exportTable('Discussion', "
            select
                p.*,
                if (p.post_author > 0, p.post_author, z_user.ID) as post_author, /* override post_author value from p.* */
                'Html' as Format,
                0 as Closed
            from :_posts as p
                left join :_postmeta as pm on pm.post_id = p.ID AND pm.meta_key = '_bbp_anonymous_email'
                left join z_user on z_user.user_email = pm.meta_value
            where post_type = 'topic'
        ;", $discussion_Map);

        // Comments
        $comment_Map = array(
            'ID' => 'CommentID',
            'post_parent_id' => 'DiscussionID',
            'post_content' => 'Body',//array('Column'=>'Body', 'Filter'=>'bbPressTrim'),
            'Format' => 'Format',
            'post_author' => 'InsertUserID',
            'post_date' => 'DateInserted',
        );
        $ex->exportTable('Comment', "
            select
                p.*,
                if (p.post_author > 0, p.post_author, z_user.ID) as post_author, /* override post_author value from p.* */
                case
                    when p.post_type = 'topic' then p.ID
                    else p.post_parent
                end as post_parent_id,
                'Html' as format
            from :_posts p
                left join :_postmeta as pm on pm.post_id = p.ID AND pm.meta_key = '_bbp_anonymous_email'
                left join z_user on z_user.user_email = pm.meta_value
            where post_type = 'topic'
                or post_type = 'reply'
            ;", $comment_Map);

        // Cleanup
        $ex->query("drop table if exists z_user;");

        // End
        $ex->endExport();
    }
}

// Closing PHP tag required. (make.php)
?>

