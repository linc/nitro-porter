<?php
/**
 * Advanced Forum (Drupal module) exporter tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Ryan Perry
 */

namespace NitroPorter\Package;

use NitroPorter\ExportController;

class AdvancedForum extends ExportController
{

    const SUPPORTED = [
        'name' => 'Advanced Forum 7.x-2.*',
        'prefix' => '',
        'CommandLine' => [
            'filepath' => array('Path to files, such as avatars.', 'Sx' => '::')
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
            'Attachments' => 0,
            'Bookmarks' => 0,
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
     * Main export process.
     *
     * @param ExportModel $ex
     * @see   $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex)
    {

        $characterSet = $ex->getCharacterSet('node');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->beginExport('', 'Advanced Forum 7.x-2.*');

        $filePath = $cdn = $this->param('filepath', '');

        // User.
        $user_Map = array();
        $ex->exportTable(
            'User', "
            select `u`.`uid` as `UserID`, `u`.`name` as `Name`, `u`.`mail` as `Email`, `u`.`pass` as `Password`,
                'drupal' as `HashMethod`, from_unixtime(`created`) as `DateInserted`,
                if(`fm`.`filename` is not null, concat('$filePath', `fm`.`filename`), NULL) as `Photo`
            from `:_users` `u`
              left join `:_file_managed` `fm` on `u`.`picture` = `fm`.`fid`", $user_Map
        );


        // Role.
        $role_Map = array();
        $ex->exportTable(
            'Role', "
            SELECT `name` AS `Name`, `rid` AS `RoleID`
            FROM `:_role` `r`
            ORDER BY `weight` ASC", $role_Map
        );


        // User Role.
        $userRole_Map = array();
        $ex->exportTable(
            'UserRole', "
         SELECT `rid` AS `RoleID`, `uid` AS `UserID`
         FROM `:_users_roles` `ur`", $userRole_Map
        );


        // Category.
        $category_Map = array();
        $ex->exportTable(
            'Category', "
            SELECT `ttd`.`tid` AS `CategoryID`, `tth`.`parent` AS `ParentCategoryID`,
              `ttd`.`name` AS `Name`, `ttd`.`weight` AS `Sort`
            FROM `:_taxonomy_term_data` `ttd`
                LEFT JOIN `:_taxonomy_vocabulary` `tv` USING (`vid`)
                LEFT JOIN `:_taxonomy_term_hierarchy` `tth` USING (`tid`)
            WHERE `tv`.`name` = 'Forums'
            ORDER BY `ttd`.`weight` ASC", $category_Map
        );


        // Discussion.
        $discussion_Map = array(
            'body_format' => array('Column' => 'Format', 'Filter' => array(__CLASS__, 'translateFormatType'))
        );

        $ex->exportTable(
            'Discussion', "
            SELECT `fi`.`nid` AS `DiscussionID`, `fi`.`tid` AS `CategoryID`, `fi`.`title` AS `Name`,
                `fi`.`comment_count` AS `CountComments`, `fdb`.`body_value` AS `Body`,
                from_unixtime(`n`.`created`) AS `DateInserted`,
                if (`n`.`created`< `n`.`changed`, from_unixtime(`n`.`changed`), NULL) AS `DateUpdated`,
                if (`fi`.`sticky` > 0,2,0) AS `Announce`,
                `n`.`uid` AS `InsertUserID`, `fdb`.`body_format`
            FROM `:_forum_index` `fi`
                JOIN `:_field_data_body` `fdb` ON (`fdb`.`bundle` = 'forum' AND `fi`.`nid`=`fdb`.`entity_id`)
                LEFT JOIN `:_node` `n` USING (`nid`)
        ", $discussion_Map
        );


        // Comment.
        $comment_Map = array(
            'comment_body_format' => array('Column' => 'Format', 'Filter' => array(__CLASS__, 'translateFormatType'))
        );
        $ex->exportTable(
            'Comment', "
            SELECT `c`.`cid` AS `CommentID`, `c`.`nid` AS `DiscussionID`, `c`.`uid` AS `InsertUserID`,
            from_unixtime(`c`.`created`) AS `DateInserted`,
            if(`c`.`created` < `c`.`changed`, from_unixtime(`c`.`changed`), NULL) AS `DateUpdated`,
            `fdcb`.`comment_body_value` AS `Body`, `fdcb`.`comment_body_format`
            FROM `:_comment` `c` JOIN `:_field_data_comment_body` `fdcb` ON (`c`.`cid` = `fdcb`.`entity_id`)
            ORDER BY `cid` ASC", $comment_Map
        );

        $ex->endExport();
    }

    /**
     * Translate from known Drupal format slugs to those compatible with Vanilla
     *
     * @param  $value Value of the current row
     * @param  $field Name associated with the current field value
     * @param  $row   Full data row columns
     * @return string Translated format slug
     */
    public static function translateFormatType($value, $field, $row)
    {
        switch ($value) {
        case 'filtered_html':
        case 'full_html':
            return 'Html';
        default:
            return 'BBCode';
        }
    }
}
