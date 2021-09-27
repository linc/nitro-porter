<?php
/**
 * Vanilla 2 exporter tool
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author Lincoln Russell, lincolnwebs.com
 */

$supported['vanilla2'] = array('name' => 'Vanilla 2', 'prefix' => 'GDN_');
$supported['vanilla2']['features'] = array(
    'Users' => 1,
    'Passwords' => 1,
    'Categories' => 1,
    'Discussions' => 1,
    'Comments' => 1,
    'Polls' => 0,
    'Roles' => 1,
    'Avatars' => 1,
    'PrivateMessages' => 1,
    'Signatures' => 1,
    'Attachments' => 1,
    'Bookmarks' => 1,
    'Permissions' => 1,
    'Badges' => 0,
    'UserNotes' => 1,
    'Ranks' => 0,
    'Groups' => 0,
    'Tags' => 0,
    'Reactions' => 0,
    'Articles' => 0,
);

class Vanilla2 extends ExportController {

    /** @var array Required tables => columns */
    protected $_sourceTables = array();

    /**
     * @param ExportModel $ex
     */
    protected function forumExport($ex) {
        $tables = array(
            'Activity',
            'Category',
            'Comment',
            'Conversation',
            'ConversationMessage',
            'Discussion',
            'Media',
            'Permission',
            'Role',
            'User',
            'UserComment',
            'UserConversation',
            'UserDiscussion',
            'UserMeta',
            'UserRole'
        );

        $ex->beginExport('', 'Vanilla 2.*', array('HashMethod' => 'Vanilla'));

        foreach ($tables as $tableName) {
            $this->exportTable($ex, $tableName);
        }

        $ex->endExport();
    }

    /**
     *
     * @param ExportModel $ex
     * @param string $tableName
     */
    protected function exportTable($ex, $tableName) {
        // Make sure the table exists.
        if (!$ex->exists($tableName)) {
            return;
        }

        $ex->exportTable($tableName, "select * from :_{$tableName}");
    }

}

// Closing PHP tag required. (make.php)
?>
