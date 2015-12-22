<?php
/**
 * Vanilla 2 exporter tool
 *
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['vanilla2'] = array('name' => 'Vanilla 2', 'prefix' => 'GDN_');
$supported['vanilla2']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'Attachments' => 1,
    'PrivateMessages' => 1,
    'Permissions' => 1,
    'UserWall' => 1,
    'UserNotes' => 1,
    'Bookmarks' => 1,
    'Signatures' => 1,
    'Passwords' => 1,
);

class Vanilla2 extends ExportController {

    /** @var array Required tables => columns */
    protected $_sourceTables = array();

    /**
     * @param ExportModel $Ex
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
     * @param ExportModel $Ex
     * @param string $TableName
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
