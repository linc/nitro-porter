<?php
/**
 * Advanced Forum (Drupal module) exporter tool.
 *
 * @copyright Vanilla Forums Inc. 2010-2014
 * @license GNU GPL2
 * @package VanillaPorter
 * @see functions.commandline.php for command line usage.
 */

$Supported['advancedforum'] = array(
    'name' => 'Advanced Forum 7.x-2.*',
    'prefix' => ''
);

$Supported['advancedforum']['CommandLine'] = array(
    'filepath' => array('Path to files, such as avatars.', 'Sx' => '::')
);

$Supported['advancedforum']['features'] = array(
    'Avatars' => 1,
    'Categories' => 1,
    'Comments' => 1,
    'Discussions' => 1,
    'Passwords' => 1,
    'Roles' => 1,
    'Users' => 1
);

class advancedforum extends ExportController {
    /**
     * Main export process.
     *
     * @param ExportModel $Ex
     * @see $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function ForumExport($Ex) {
        $CharacterSet = $Ex->GetCharacterSet(':_node');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        $Ex->BeginExport('', 'Advanced Forum 7.x-2.*');

        $FilePath = $cdn = $this->Param('filepath', '');

        // User.
        $User_Map = array();
        $Ex->ExportTable('User', "
            select `u`.`uid` as `UserID`, `u`.`name` as `Name`, `u`.`mail` as `Email`, `u`.`pass` as `Password`,
                'drupal' as `HashMethod`, from_unixtime(`created`) as `DateInserted`,
                if(`fm`.`filename` is not null, concat('$FilePath', `fm`.`filename`), NULL) as `Photo`
            from `:_users` `u`
              left join `:_file_managed` `fm` on `u`.`picture` = `fm`.`fid`", $User_Map);


        // Role.
        $Role_Map = array();
        $Ex->ExportTable('Role', "
            SELECT `name` AS `Name`, `rid` AS `RoleID`
            FROM `:_role` `r`
            ORDER BY `weight` ASC", $Role_Map);


        // User Role.
        $UserRole_Map = array();
        $Ex->ExportTable('UserRole', "
         SELECT `rid` AS `RoleID`, `uid` AS `UserID`
         FROM `:_users_roles` `ur`", $UserRole_Map);


        // Category.
        $Category_Map = array();
        $Ex->ExportTable('Category', "
            SELECT `ttd`.`tid` AS `CategoryID`, `tth`.`parent` AS `ParentCategoryID`,
              `ttd`.`name` AS `Name`, `ttd`.`weight` AS `Sort`
            FROM `:_taxonomy_term_data` `ttd`
                LEFT JOIN `:_taxonomy_vocabulary` `tv` USING (`vid`)
                LEFT JOIN `:_taxonomy_term_hierarchy` `tth` USING (`tid`)
            WHERE `tv`.`name` = 'Forums'
            ORDER BY `ttd`.`weight` ASC", $Category_Map);


        // Discussion.
        $Discussion_Map = array(
            'body_format' => array('Column' => 'Format', 'Filter' => array(__CLASS__, 'TranslateFormatType'))
        );

        $Ex->ExportTable('Discussion', "
            SELECT `fi`.`nid` AS `DiscussionID`, `fi`.`tid` AS `CategoryID`, `fi`.`title` AS `Name`,
                `fi`.`comment_count` AS `CountComments`, `fdb`.`body_value` AS `Body`,
                from_unixtime(`n`.`created`) AS `DateInserted`,
                if (`n`.`created`< `n`.`changed`, from_unixtime(`n`.`changed`), NULL) AS `DateUpdated`,
                if (`fi`.`sticky` > 0,2,0) AS `Announce`,
                `n`.`uid` AS `InsertUserID`, `fdb`.`body_format`
            FROM `:_forum_index` `fi`
                JOIN `:_field_data_body` `fdb` ON (`fdb`.`bundle` = 'forum' AND `fi`.`nid`=`fdb`.`entity_id`)
                LEFT JOIN `:_node` `n` USING (`nid`)
        ", $Discussion_Map);


        // Comment.
        $Comment_Map = array(
            'comment_body_format' => array('Column' => 'Format', 'Filter' => array(__CLASS__, 'TranslateFormatType'))
        );
        $Ex->ExportTable('Comment', "
            SELECT `c`.`cid` AS `CommentID`, `c`.`nid` AS `DiscussionID`, `c`.`uid` AS `InsertUserID`,
            from_unixtime(`c`.`created`) AS `DateInserted`,
            if(`c`.`created` < `c`.`changed`, from_unixtime(`c`.`changed`), NULL) AS `DateUpdated`,
            `fdcb`.`comment_body_value` AS `Body`, `fdcb`.`comment_body_format`
            FROM `:_comment` `c` JOIN `:_field_data_comment_body` `fdcb` ON (`c`.`cid` = `fdcb`.`entity_id`)
            ORDER BY `cid` ASC", $Comment_Map);

        $Ex->EndExport();
    }

    /**
     * Translate from known Drupal format slugs to those compatible with Vanilla
     * @param $Value Value of the current row
     * @param $Field Name associated with the current field value
     * @param $Row   Full data row columns
     * @return string Translated format slug
     */
    public static function TranslateFormatType($Value, $Field, $Row) {
        switch ($Value) {
            case 'filtered_html':
            case 'full_html':
                return 'Html';
            default:
                return 'BBCode';
        }
    }
}

?>
