<?php
/**
 * Vanilla 2 exporter tool for Drupal 7
 *
 * @copyright 2018 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['drupal7'] = array('name' => 'Drupal 7', 'prefix' => '');
$supported['drupal7']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'Signatures' => 1,
    'Passwords' => 1,
);

class Drupal7 extends ExportController {
    /**
     * @param ExportModel $ex
     */
    protected function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('comment');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Begin.
        $ex->beginExport('', 'Drupal 7');

        // Users.
        // TODO validate password hashing didn't change between drupal 6 and drupal 7.
        $ex->exportTable('User', "
            select
                uid as UserID,
                name as Name,
                pass as Password,
                nullif(concat('drupal_profile/',if(picture = 0, null, picture)), 'drupal_profile/') as Photo,
                concat('md5$$', pass) as Password,
                'Django' as HashMethod,
                mail as Email,
                from_unixtime(created) as DateInserted,
                from_unixtime(login) as DateLastActive
            from :_users
            where uid > 0 and status = 1
        ");

        // Signatures.
        $ex->exportTable('UserMeta', "
            select 
                uid as UserID,
                signature as Value,
                'Plugin.Signatures.Sig' as Name
            from :_users u
            where uid > 0 and status = 1 and signature is not null and signature <> ''
            
            union
            
            select 
                uid as UserID,
                'Html' as Value,
                'Plugins.Signatures.Format' as Name
            from :_users u
            where uid > 0 and status = 1 and signature is not null and signature <> ''
        ");

        // Roles.
        $ex->exportTable('Role', "
            select 
                rid as RoleID,
                name as Name 
            from :_role
        ");

        // User Role.
        $ex->exportTable('UserRole', "
            select 
                uid as UserID,
                rid as RoldID
            from :_users_roles
         ");

        // Categories.
        $ex->exportTable('Category', "
            select 
                t.tid as CategoryID,
                t.name as Name,
                t.description as Description,
                if(th.parent = 0, null, th.parent) as ParentCategoryID
            from :_taxonomy_term_data t
            join :_taxonomy_term_hierarchy th on th.tid = t.tid
            join :_taxonomy_vocabulary tv on tv.vid = t.vid
            where tv.name = 'Forums'
        ");

        // Discussion and comment format differ from each other.
        // Discussions.
        $ex->exportTable('Discussion', "
            select
                n.nid as DiscussionID,
                n.uid as InsertUserID,
                from_unixtime(n.created) as DateInserted,
                from_unixtime(n.changed) as DateUpdated,
                if(n.sticky = 1, 2, 0) as Announce,
                f.tid as CategoryID,
                n.title as Name,
                frv.body_value as Body,
                'Html' as Format
            from :_node n
            join :_forum f on f.nid = n.nid
            join :_field_revision_body frv on frv.revision_id = n.vid
            where n.type = 'forum' and n.moderate = 0 and frv.deleted = 0
        ");

        // Comments.
        $ex->exportTable('Comment', "
            select
                c.cid as CommentID,
                c.nid as DiscussionID,
                c.uid as InsertUserID,
                from_unixtime(c.created) as DateInserted,
                from_unixtime(c.changed) as DateUpdated,
                frcb.comment_body_value as Body,
                'BBcode' as Format
            from comment c
            join field_revision_comment_body frcb on frcb.entity_id = c.cid
            where c.status = 1 and frcb.deleted = 0
         ");

        // Media.
        $ex->exportTable('Media', "
            select
				fm.fid as MediaID,
				fm.filemime as Type,
                fdff.entity_id as ForeignID,
                if(fdff.entity_type = 'node', 'discussion', 'comment') as ForeinTable,
                fm.filename as Name,
                concat('drupal_attachments/',substring(fm.uri, 10)) as Path,
                fm.filesize as Size,
                from_unixtime(timestamp) as DateInserted
            from file_managed fm
            join field_data_field_file fdff on fdff.field_file_fid = fm.fid
            where (fdff.entity_type = 'node' or fdff.entity_type = 'comment') and (fdff.bundle = 'comment_node_forum' or fdff.bundle = 'forum')
         ");
    }
}

// Closing PHP tag required. (make.php)
?>
