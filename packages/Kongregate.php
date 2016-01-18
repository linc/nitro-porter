<?php
/**
 * Kongregate exporter tool.
 *
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 *
 * Prior of using this package:
 * CREATE INDEX index_migration_post_number_topic ON posts (post_number, topic_id);
 *
 */

$supported['kongregate'] = array('name'=> 'kongregate', 'prefix'=>'');
$supported['kongregate']['features'] = array('Users' => 1);

class Kongregate extends ExportController {
    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see $_structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex) {
        // Get the characterset for the comments.
        // Usually the comments table is the best target for this.
        $characterSet = $ex->getCharacterSet('posts');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Reiterate the platform name here to be included in the porter file header.
        $ex->beginExport('', 'Kongregate');


        // User.
        $user_Map = array(
            //'Source' => 'Vanilla',
        );
        $ex->exportTable('User', "
            select
                'DaazKu' as Name,
                'ICRT3238457TB2348RT23498TCR89TB' as Password,
                'Reset' as HashMethod,
                'alexandre.c@vanillaforums.com' as Email
            from dual
        ", $user_Map);
//
//
//        // Role.
//        $role_Map = array(
//            //'Source' => 'Vanilla',
//        );
//        $ex->exportTable('Role', "
//            select *
//            from :_tblGroup
//        ", $role_Map);
//
//
//        // User Role.
//        $userRole_Map = array(
//            //'SourceName' => 'VanillaName',
//        );
//        $ex->exportTable('UserRole', "
//            select u.*
//            from :_tblAuthor u
//        ", $userRole_Map);

        // Categories
        $category_Map = array(
            'forum_id' => 'CategoryID',
            'forum_name' => 'Name',
            'forum_desc' => array('Column' => 'Description', 'Filter' => 'HTMLDecoder'),
            'forum_pos' => array('Column' => 'Sort', 'Filter' => array($this, 'SortFixer')),
            'parent_forum_id' => 'ParentCategoryID',
        );
        $ex->exportTable('Category', "
            select
                1 as forum_id,
                'About Kongregate' as forum_name,
                null as forum_desc,
                null as forum_pos,
                null as parent_forum_id
            from dual

            union all

            select 2, 'Games', null, null, null from dual

            union all

            select 3, 'Game Creation', null, null, null from dual

            union all

            select 4, 'Non-gaming', null, null, null from dual

            union all

            select tmp.* from (
                select
                    id+4 as forum_id,
                    name as forum_name,
                    description as forum_desc,
                    position,
                    case
    					when group_name = 'About Kongregate' then 1
    					when group_name = 'Game Creation' then 2
    					when group_name = 'Games' then 3
    					when group_name = 'Non-gaming' then 4
    					when group_name = '' then parent_forum_id+4
					end as parent_forum_id
                from forums
                where group_name != 'Guilds'
                order by
                    parent_forum_id,
                    group_name,
                    position

            ) as tmp

            order by
                parent_forum_id,
                forum_pos,
                forum_name
        ", $category_Map);


        // Discussion.
        $discussion_Map = array(
            'id' => 'DiscussionID',
            'forum_id' => 'CategoryID',
            'user_id' => 'InsertUserID',
            'title' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'discussion_body' => array('Column' => 'Body', 'Filter' => 'HTMLDecoder'),
            'format' => 'Format',
            'locked' => 'Closed',
            'created_at' => 'DateInserted',
            'hits' => 'CountViews',
        );

        // TODO: Check what to do about hidden topics

        $ex->exportTable('Discussion', "
            select
                topics.id,
                topics.forum_id + 4 as forum_id,
                topics.user_id,
                topics.title,
                posts.body_html as discussion_body,
                'Html' as format,
                topics.locked,
                topics.created_at,
                topics.hits
            from topics
                inner join posts on posts.post_number = 1 and posts.topic_id = topics.id
            where
                hidden = 0
        ", $discussion_Map);


        // Comments
        $comment_Map = array(
            'id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'body_html' => array('Column' => 'Body', 'Filter' => 'HTMLDecoder'),
            'format' => 'Format',
            'user_id' => 'InsertUserID'
        );
        $ex->exportTable('Comment', "
            select
                id,
                topic_id,
                body_html,
                'Html' as format,
                user_id
            from posts
                where post_number > 1

        ", $comment_Map);


        // UserDiscussion.

        // Permission.

        // UserMeta.

        // Media.

        // Conversations.

        // Polls.

        $ex->endExport();
    }

    /**
     * Filter used by $category_Map to generate a correct sort order.
     *
     * @param string $value Ignored.
     * @param string $field Ignored.
     * @param array $row Ignored.
     *
     * @return string .
     */
    public function sortFixer($value, $field, $row) {
        static $i = 0;

        return ++$i;
    }
}

// Closing PHP tag required. (make.php)
?>
