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

class Vanilla extends Source
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
            'Polls' => 'Cloud only',
            'Roles' => 1,
            'Avatars' => 1,
            'PrivateMessages' => 1,
            'Signatures' => 1,
            'Attachments' => 1,
            'Bookmarks' => 1,
            'Permissions' => 1,
            'Badges' => 'Cloud or YAGA',
            'UserNotes' => 1,
            'Ranks' => 'Cloud or YAGA',
            'Groups' => 0,
            'Tags' => 1,
            'Reactions' => 'Cloud or YAGA',
        ]
    ];

    /**
     * @var array Required tables => columns
     */
    public $sourceTables = array();

    /**
     * @param ExportModel $ex
     */
    public function run(ExportModel $ex)
    {
        // Core tables essentially map to our intermediate format as-is.
        $tables = [
            //'Activity',
            'Category',
            'Comment',
            'Conversation',
            'ConversationMessage',
            'Discussion',
            'Media',
            //'Permission',
            'Role',
            'Tag',
            'TagDiscussion',
            'User',
            'UserComment',
            'UserConversation',
            'UserDiscussion',
            'UserMeta',
            'UserRole',
        ];
        foreach ($tables as $tableName) {
            if ($ex->exists($tableName)) {
                $ex->export($tableName, "select * from :_{$tableName}");
            }
        }

        $this->badges($ex);
        $this->ranks($ex);
        $this->reactions($ex);
        $this->polls($ex);
    }

    /**
     * Badges support for cloud + Yaga.
     *
     * @param ExportModel $ex
     */
    public function badges(ExportModel $ex)
    {
        if ($ex->exists('Badge')) {
            // Vanilla Cloud
            $ex->export('Badge', "select * from :_Badge");
            $ex->export('UserBadge', "select * from :_UserBadge");
        } elseif ($ex->exists('YagaBadge')) {
            // https://github.com/bleistivt/yaga
            $map = [
                'Description' => 'Body',
                'RuleClass' => 'Type',
                'RuleCriteria' => 'Attributes', // This probably doesn't actually work, but we'll try.
                'AwardValue' => 'Points',
                'Enabled' => 'Active',
            ];
            // Yaga is missing a couple columns we need.
            $ex->export('Badge', "select *,
                NOW() as DateInserted,
                1 as InsertUserID,
                Description as Body,
                Enabled as Visible
                from :_YagaBadge", $map);
            $ex->export('UserBadge', "select *,
                DateInserted as DateCompleted
                from :_YagaBadgeAward");
        }
    }

    /**
     * Ranks support for cloud + Yaga.
     *
     * @param ExportModel $ex
     */
    public function ranks(ExportModel $ex)
    {
        if ($ex->exists('Rank')) {
            // Vanilla Cloud
            $ex->export('Rank', "select * from :_Rank");
        } elseif ($ex->exists('YagaRank')) {
            // https://github.com/bleistivt/yaga
            $map = [
                'Description' => 'Body',
                'Sort' => 'Level',
                // Use 'Name' as both 'Name' and 'Label' (via SQL below)
            ];
            $ex->export('Rank', "select *, Name as Label from :_YagaRank", $map);
        }
    }

    /**
     * Reactions support for cloud + Yaga.
     *
     * @param ExportModel $ex
     */
    public function reactions(ExportModel $ex)
    {
        if ($ex->exists('ReactionType')) {
            // Vanilla Cloud & later open source
            $ex->export('ReactionType', "select * from :_ReactionType");
            //$ex->export('Reaction', "select * from :_Tag where Type='Reaction'");
            $ex->export('UserTag', "select * from :_UserTag");
        } elseif ($ex->exists('YagaReaction')) {
            // https://github.com/bleistivt/yaga
            // Shortcut use of Tag table by setting ActionID = TagID.
            // This wouldn't work for exporting a Yaga-based Vanilla install to a "standard" reactions Vanilla install,
            // but I have to assume no one is using Porter for that anyway.
            // Other Targets should probably directly join ReactionType & UserTag on TagID anyway.
            // Yaga also lacks an 'active/enabled' field so assume they're all 'on'.
            $ex->export('ReactionType', "select *,
                ActionID as TagID,
                1 as Active
                from :_YagaAction"); // Name & Description only
            $map = [
                'ParentID' => 'RecordID',
                'ParentType' => 'RecordType',
                'InsertUserID' => 'UserID',
                'ParentScore' => 'Total',
                'ActionID' => 'TagID',
            ];
            $ex->export('UserTag', "select * from :_YagaReaction", $map);
        }
    }

    /**
     * Polls support for cloud + "DiscussionPolls".
     *
     * @param ExportModel $ex
     */
    public function polls(ExportModel $ex)
    {
        if ($ex->exists('Poll')) {
            // SaaS
            $ex->export('Poll', "select * from :_Poll");
            $ex->export('PollOption', "select * from :_PollOption");
            $ex->export('PollVote', "select * from :_PollVote");
        } elseif ($ex->exists('DiscussionPolls')) {
            // @todo https://github.com/hgtonight/Plugin-DiscussionPolls
            //$ex->export('Poll', "select * from :_DiscussionPollQuestions");
            //$ex->export('PollOption', "select * from :_DiscussionPollQuestionOptions");
            //$ex->export('PollVote', "select * from :_DiscussionPollAnswers");
        }
    }
}
