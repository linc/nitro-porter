<?php
/**
 * Vanilla 2 exporter tool
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author Lincoln Russell, lincolnwebs.com
 */

namespace NitroPorter\Package;

use NitroPorter\ExportController;

class Vanilla2 extends ExportController {

    const SUPPORTED = [
        'name' => 'Vanilla 2',
        'prefix' => 'GDN_',
        'features' => [
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
        ]
    ];

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

