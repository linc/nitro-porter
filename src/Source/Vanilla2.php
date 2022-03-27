<?php

/**
 * Vanilla 2+ exporter tool
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class Vanilla2 extends Source
{
    public const SUPPORTED = [
        'name' => 'Vanilla 2+',
        'prefix' => 'GDN_',
        'charset_table' => 'Comment',
        'hashmethod' => 'Vanilla',
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 1,
            'Roles' => 1,
            'Avatars' => 1,
            'PrivateMessages' => 1,
            'Signatures' => 1,
            'Attachments' => 1,
            'Bookmarks' => 1,
            'Permissions' => 1,
            'Badges' => 1,
            'UserNotes' => 1,
            'Ranks' => 1,
            'Groups' => 0,
            'Tags' => 1,
            'Reactions' => 1,
            'Articles' => 0,
        ]
    ];

    /**
     * @var array Required tables => columns
     */
    public $sourceTables = array();

    /**
     * @param ExportModel $ex
     */
    public function run($ex)
    {
        $tables = array(
            //'Activity',
            'Badge', // SaaS-only
            'Category',
            'Comment',
            'Conversation',
            'ConversationMessage',
            'Discussion',
            'Media',
            //'Permission',
            'Poll', // SaaS-only
            'PollOption', // SaaS-only
            'PollVote', // SaaS-only
            'ReactionType',
            'Rank', // SaaS-only
            'Role',
            'Tag',
            'TagDiscussion',
            'User',
            'UserBadge', // SaaS-only
            'UserComment',
            'UserConversation',
            'UserDiscussion',
            'UserMeta',
            'UserRole'
        );

        foreach ($tables as $tableName) {
            if ($ex->exists($tableName)) {
                $ex->export($tableName, "select * from :_{$tableName}");
            }
        }
    }
}
