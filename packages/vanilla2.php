<?php
/**
 * Vanilla 2 exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['vanilla2'] = array('name' => 'Vanilla 2', 'prefix' => 'GDN_');
$Supported['vanilla2']['features'] = array(
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
    protected $_SourceTables = array();

    /**
     * @param ExportModel $Ex
     */
    protected function ForumExport($Ex) {
        $Tables = array(
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

        $Ex->BeginExport('', 'Vanilla 2.*', array('HashMethod' => 'Vanilla'));

        foreach ($Tables as $TableName) {
            $this->ExportTable($Ex, $TableName);
        }

        $Ex->EndExport();
    }

    /**
     *
     * @param ExportModel $Ex
     * @param string $TableName
     */
    protected function ExportTable($Ex, $TableName) {
        // Make sure the table exists.
        if (!$Ex->Exists($TableName)) {
            return;
        }

        $Ex->ExportTable($TableName, "select * from :_{$TableName}");
    }

}

?>
