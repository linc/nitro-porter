<?php
/**
 * Joomla Kunena exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['kunena'] = array('name' => 'Joomla Kunena', 'prefix' => 'jos_');
$Supported['kunena']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'Attachments' => 1,
    'Bookmarks' => 1,
    'Passwords' => 1,
);

class Kunena extends ExportController {
    /**
     * @param ExportModel $Ex
     */
    public function ForumExport($Ex) {
        $Ex->DestPrefix = 'jos';

        $Ex->BeginExport('', 'Joomla Kunena', array('HashMethod' => 'joomla'));

        // User.
        $User_Map = array(
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
        $Ex->ExportTable('User', "
         SELECT
            u.*,
            case when ku.avatar <> '' then concat('kunena/avatars/', ku.avatar) else null end as `Photo`,
            case u.usertype when 'superadministrator' then 1 else 0 end as admin,
            coalesce(ku.banned, 0) as banned,
            ku.birthdate,
            !ku.hideemail as showemail
         FROM :_users u
         left join :_kunena_users ku
            on ku.userid = u.id", $User_Map);

        // Role.
        $Role_Map = array(
            'rank_id' => 'RoleID',
            'rank_title' => 'Name',
        );
        $Ex->ExportTable('Role', "select * from :_kunena_ranks", $Role_Map);

        // UserRole.
        $UserRole_Map = array(
            'id' => 'UserID',
            'rank' => 'RoleID'
        );
        $Ex->ExportTable('UserRole', "
         select *
         from :_users u", $UserRole_Map);

        // Permission.
//      $Ex->ExportTable('Permission',
//      "select 2 as RoleID, 'View' as _Permissions
//      union
//      select 3 as RoleID, 'View' as _Permissions
//      union
//      select 16 as RoleID, 'All' as _Permissions", array('_Permissions' => array('Column' => '_Permissions', 'Type' => 'varchar(20)')));

        // Category.
        $Category_Map = array(
            'id' => 'CategoryID',
            'parent' => 'ParentCategoryID',
            'name' => 'Name',
            'ordering' => 'Sort',
            'description' => 'Description',

        );
        $Ex->ExportTable('Category', "
         select * from :_kunena_categories", $Category_Map);

        // Discussion.
        $Discussion_Map = array(
            'id' => 'DiscussionID',
            'catid' => 'CategoryID',
            'userid' => 'InsertUserID',
            'subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'time' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'ip' => 'InsertIPAddress',
            'locked' => 'Closed',
            'hits' => 'CountViews',
            'modified_by' => 'UpdateUserID',
            'modified_time' => array('Column' => 'DateUpdated', 'Filter' => 'TimestampToDate'),
            'message' => 'Body',
            'Format' => 'Format'
        );
        $Ex->ExportTable('Discussion', "
         select
            t.*,
            txt.message,
            'BBCode' as Format
         from :_kunena_messages t
         left join :_kunena_messages_text txt
            on t.id = txt.mesid
         where t.thread = t.id", $Discussion_Map);

        // Comment.
        $Comment_Map = array(
            'id' => 'CommentID',
            'thread' => 'DiscussionID',
            'userid' => 'InsertUserID',
            'time' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
            'ip' => 'InsertIPAddress',
            'modified_by' => 'UpdateUserID',
            'modified_time' => array('Column' => 'DateUpdated', 'Filter' => 'TimestampToDate'),
            'message' => 'Body',
            'Format' => 'Format'
        );
        $Ex->ExportTable('Comment', "
         select
            t.*,
            txt.message,
            'BBCode' as Format
         from :_kunena_messages t
         left join :_kunena_messages_text txt
            on t.id = txt.mesid
         where t.thread <> t.id", $Comment_Map);

        // UserDiscussion.
        $UserDiscussion_Map = array(
            'thread' => 'DiscussionID',
            'userid' => 'UserID'
        );
        $Ex->ExportTable('UserDiscussion', "
         select t.*, 1 as Bookmarked
         from :_kunena_subscriptions t", $UserDiscussion_Map);

        // Media.
        $Media_Map = array(
            'id' => 'MediaID',
            'mesid' => 'ForeignID',
            'userid' => 'InsertUserID',
            'size' => 'Size',
            'path2' => array('Column' => 'Path', 'Filter' => 'UrlDecode'),
            'thumb_path' => array('Column' => 'ThumbPath', 'Filter' => array($this, 'FilterThumbnailData')),
            'thumb_width' => array('Column' => 'ThumbWidth', 'Filter' => array($this, 'FilterThumbnailData')),
            'filetype' => 'Type',
            'filename' => array('Column' => 'Name', 'Filter' => 'UrlDecode'),
            'time' => array('Column' => 'DateInserted', 'Filter' => 'TimestampToDate'),
        );
        $Ex->ExportTable('Media', "
         select
            a.*,
            concat(a.folder, '/', a.filename) as path2,
            'local' as StorageMethod,
            case when m.id = m.thread then 'discussion' else 'comment' end as ForeignTable,
            m.time,
            concat(a.folder, '/', a.filename) as thumb_path,
            128 as thumb_width
         from :_kunena_attachments a
         join :_kunena_messages m
            on m.id = a.mesid", $Media_Map);

        $Ex->EndExport();
    }

    /**
     * Filter used by $Media_Map to replace value for ThumbPath and ThumbWidth when the file is not an image.
     *
     * @access public
     * @see ExportModel::_ExportTable
     *
     * @param string $Value Current value
     * @param string $Field Current field
     * @param array $Row Contents of the current record.
     * @return current value of the field or null if the file is not an image.
     */
    public function FilterThumbnailData($Value, $Field, $Row) {
        if (strpos($Row['filetype'], 'image/') === 0) {
            return $Value;
        } else {
            return null;
        }
    }
}

?>
