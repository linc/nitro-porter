<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Target;

use Porter\ExportModel;
use Porter\Formatter;
use Porter\Target;

class Flarum extends Target
{
    public const SUPPORTED = [
        'name' => 'Flarum',
        'prefix' => 'FLA_',
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 0,
            'PrivateMessages' => 1,
            'Bookmarks' => 1,
            'Badges' => 1,
        ]
    ];

    protected const FLAGS = [
        'hasDiscussionBody' => false,
    ];

    /**
     * Check for issues that will break the import.
     *
     * @param ExportModel $ex
     */
    public function validate(ExportModel $ex)
    {
        $this->uniqueUserNames($ex);
        $this->uniqueUserEmails($ex);
    }

    /**
     * Flarum must have unique usernames.
     *
     * Unsure this could get automated. You'd have to determine which has/have data attached and possibly merge.
     * You'd also need more data from findDuplicates, especially the IDs.
     * Folks are just gonna need to manually edit their existing forum data for now to rectify dupe issues.
     *
     * @param ExportModel $ex
     */
    public function uniqueUserNames(ExportModel $ex)
    {
        $allowlist = ['[Deleted User]', '[DeletedUser]', '-Deleted-User-']; // @see fixDuplicateDeletedNames()
        $dupes = array_diff($ex->findDuplicates('PORT_User', 'Name'), $allowlist);
        if (!empty($dupes)) {
            // @todo Do nicer error log + halt here to allow export to output report.
            //exit('Import halted. Found duplicates for user.name, which Flarum cannot import. Fix these first: '
            //    . implode(', ', $dupes));
        }
    }

    /**
     * Flarum must have unique emails.
     *
     * @see uniqueUserNames
     *
     * @param ExportModel $ex
     */
    public function uniqueUserEmails(ExportModel $ex)
    {
        $dupes = $ex->findDuplicates('PORT_User', 'Email');
        if (!empty($dupes)) {
            // @todo Do nicer error log + halt here to allow export to output report.
            //exit('Import halted. Found duplicates for user.email, which Flarum cannot import. Fix these first: '
            //    . implode(', ', $dupes));
        }
    }

    /**
     * Main import process.
     */
    public function run(ExportModel $ex)
    {
        $this->users($ex);
        $this->roles($ex); // Groups
        $this->categories($ex); // Tags

        // Singleton factory; big timing issue; depends on Users being done.
        Formatter::instance($ex); // @todo Hook for pre-UGC import?

        $this->discussions($ex);
        $this->bookmarks($ex); // flarum/subscriptions
        $this->comments($ex); // Posts

        if ($ex->targetExists('PORT_Badge')) {
            $this->badges($ex); // 17development/flarum-user-badges
        }
        if ($ex->targetExists('PORT_Poll')) {
            $this->polls($ex); // fof/polls
        }
        if ($ex->targetExists('PORT_ReactionType')) {
            $this->reactions($ex); //
        }
        $this->privateMessages($ex);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'username' => 'varchar(100)',
            'email' => 'varchar(100)',
            'is_email_comfirmed' => 'tinyint',
            'password' => 'varchar(100)',
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
        $map = [
            'UserID' => 'id',
            'Name' => 'username',
            'Email' => 'email',
            'Password' => 'password',
            'DateInserted' => 'joined_at',
            'DateLastActive' => 'last_seen_at',
            'Confirmed' => 'is_email_confirmed',
            'CountDiscussions' => 'discussion_count',
            'CountComments' => 'comment_count',
        ];
        $filters = [
            'Name' => 'fixDuplicateDeletedNames',
            'Email' => 'fixNullEmails',
        ];
        $query = $ex->dbImport()->table('PORT_User')->select('*');

        $ex->import('users', $query, $structure, $map, $filters);
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'name_singular' => 'varchar(100)',
            'name_plural' => 'varchar(100)',
        ];
        $map = [
            'RoleID' => 'id',
            'Name' => 'name_singular',
            'Plural' => 'name_plural',
        ];
        $query = $ex->dbImport()->table('PORT_Role')->select('*', 'Name as Plural');

        $ex->dbImport()->unprepared("SET foreign_key_checks = 0");
        $ex->import('groups', $query, $structure, $map);

        // User Role.
        $structure = [
            'user_id' => 'int',
            'group_id' => 'int',
        ];
        $map = [
            'UserID' => 'user_id',
            'RoleID' => 'group_id',
        ];
        $query = $ex->dbImport()->table('PORT_UserRole')->select('*');

        $ex->dbImport()->unprepared("SET foreign_key_checks = 0");
        $ex->import('group_user', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'name' => 'varchar(100)',
            'slug' => 'varchar(100)',
            'description' => 'text',
            'parent_id' => 'int',
            'position' => 'int',
            'discussion_count' => 'int',
        ];
        $map = [
            'CategoryID' => 'id',
            'Name' => 'name',
            'UrlCode' => 'slug',
            'Description' => 'description',
            'ParentCategoryID' => 'parent_id',
            'Sort' => 'position',
            'CountDiscussions' => 'discussion_count',
        ];
        $query = $ex->dbImport()->table('PORT_Category')->select('*');

        $ex->import('tags', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'user_id' => 'int',
            'title' => 'varchar(200)',
            'tag_id' => 'int',
            'is_sticky' => 'tinyint', // flarum/sticky
            'is_locked' => 'tinyint', // flarum/lock
        ];
        $map = [
            'DiscussionID' => 'id',
            'InsertUserID' => 'user_id',
            'Name' => 'title',
            'Announce' => 'is_sticky', // Flarum doesn't mind if this is '2' so straight map it.
            'Closed' => 'is_locked',
        ];
        if ($ex->targetExists($ex->tarPrefix . 'discussions', ['view_count'])) {
            // flarumite/simple-discussion-views
            $structure['view_count'] = 'int';
            $map['CountViews'] = 'view_count';
        }

        $query = $ex->dbImport()->table('PORT_Discussion')->select('*');

        $ex->import('discussions', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function bookmarks(ExportModel $ex): void
    {
        $structure = [
            'discussion_id' => 'int',
            'user_id' => 'int',
            'last_read_at' => 'datetime',
            'subscription' => [null, 'follow', 'ignore'],
        ];
        $map = [
            'DiscussionID' => 'discussion_id',
            'InsertUserID' => 'user_id',
            'DateLastViewed' => 'last_read_at',
        ];
        $query = $ex->dbImport()->table('PORT_UserDiscussion')
            ->select('*', ($ex->dbImport()->raw("if (Bookmarked > 0, 'follow', null) as subscription")));

        $ex->import('discussion_user', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'discussion_id' => 'int',
            'user_id' => 'int',
            'created_at' => 'datetime',
            'edited_at' => 'datetime',
            'edited_user_id' => 'int',
            'type' => 'varchar(100)',
            'content' => 'longText',
        ];
        $map = [
            'CommentID' => 'id',
            'DiscussionID' => 'discussion_id',
            'InsertUserID' => 'user_id',
            'DateInserted' => 'created_at',
            'DateUpdated' => 'edited_at',
            'UpdateUserID' => 'edited_user_id',
            'Body' => 'content'
        ];
        $filters = [
            'Body' => 'filterFlarumContent',
        ];
        $query = $ex->dbImport()->table('PORT_Comment')
            ->select(
                'CommentID',
                'DiscussionID',
                'InsertUserID',
                'DateInserted',
                'DateUpdated',
                'UpdateUserID',
                'Body',
                'Format',
                $ex->dbImport()->raw('"comment" as type')
            );

        // Extract OP from the discussion.
        if ($this->getDiscussionBodyMode()) {
            // Get highest CommentID.
            $result = $ex->dbImport()
                ->table('PORT_Comment')
                ->select($ex->dbImport()->raw('max(CommentID) as LastCommentID'))
                ->first();

            // Use DiscussionID but fast-forward it past highest CommentID to insure it's unique.
            $discussions = $ex->dbImport()->table('PORT_Discussion')
                ->select(
                    $ex->dbImport()->raw('(DiscussionID + ' . $result->LastCommentID . ') as CommentID'),
                    'DiscussionID',
                    'InsertUserID',
                    'DateInserted',
                    'DateUpdated',
                    'UpdateUserID',
                    'Body',
                    'Format',
                    $ex->dbImport()->raw('"comment" as type')
                );

            // Combine discussions.body with the comments to get all posts.
            $query->union($discussions);
        }

        $ex->import('posts', $query, $structure, $map, $filters);
    }

    /**
     * @param ExportModel $ex
     */
    protected function badges(ExportModel $ex): void
    {
        // Badge Groups
        //

        // Badges
        $structure = [
            'id' => 'int',
            'name' => 'varchar(200)',
            'description' => 'text',
            'points' => 'int',
            'created_at' => 'datetime',
            'is_visible' => 'tinyint',
        ];
        $map = [
            'BadgeID' => 'discussion_id',
            'InsertUserID' => 'user_id',
            'DateLastViewed' => 'last_read_at',
            'Visible' => 'is_visible',
        ];
        $query = $ex->dbImport()->table('PORT_Badge')->select('*');

        $ex->import('badges', $query, $structure, $map);

        // User Badges
        $structure = [
            'badge_id' => 'int',
            'user_id' => 'int',
            'assigned_at' => 'datetime',
            'description' => 'text',
        ];
        $map = [
            'BadgeID' => 'badge_id',
            'UserID' => 'user_id',
            'Reason' => 'description',
            'DateCompleted' => 'assigned_at',
        ];
        $query = $ex->dbImport()->table('PORT_UserBadge')->select('*');

        $ex->import('badge_user', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function polls(ExportModel $ex): void
    {
        // Polls
        $structure = [
            'id' => 'int',
            'question' => 'varchar(200)',
            'discussion_id' => 'int',
            'user_id' => 'int',
            //'public_poll' => 'tinyint', // Map to "Anonymous" somehow?
            'end_date' => 'datetime', // Using date created here will close all polls, but work fine.
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'vote_count' => 'int',
        ];
        $map = [
            'PollID' => 'id',
            'Name' => 'question',
            'DiscussionID' => 'discussion_id',
            'InsertUserID' => 'user_id',
            'DateInserted' => 'created_at',
            'DateUpdated' => 'updated_at',
            'CountVotes' => 'vote_count',
        ];
        $query = $ex->dbImport()->table('PORT_Poll')->select('*', 'DateInserted as end_date');

        $ex->import('polls', $query, $structure, $map);

        // Poll Options
        $structure = [
            'id' => 'int',
            'answer' => 'varchar(200)',
            'poll_id' => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'vote_count' => 'int',
        ];
        $map = [
            'PollOptionID' => 'id',
            'PollID' => 'poll_id',
            'Body' => 'answer',
            'DateInserted' => 'created_at',
            'DateUpdated' => 'updated_at',
            'CountVotes' => 'vote_count',
        ];
        $query = $ex->dbImport()->table('PORT_PollOption')->select('*');

        $ex->import('poll_options', $query, $structure, $map);

        // Poll Votes
        $structure = [
            //id
            'poll_id' => 'int',
            'option_id' => 'int',
            'user_id' => 'int',
            //'created_at' => 'datetime',
            //'updated_at' => 'datetime',
        ];
        $map = [
            'PollOptionID' => 'option_id',
            'UserID' => 'user_id',
        ];
        $query = $ex->dbImport()->table('PORT_PollVote')
            ->leftJoin('PORT_PollOption', 'PORT_PollVote.PollOptionID', '=', 'PORT_PollOption.PollOptionID')
            ->select('PORT_PollVote.*', 'PORT_PollOption.PollID as poll_id');

        $ex->import('poll_votes', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    public function reactions(ExportModel $ex)
    {
        // Reaction Types
        $structure = [
            'id' => 'int',
            'identifier' => 'varchar(200)',
            'type' => 'varchar(200)',
            'enabled' => 'tinyint',
            'display' => 'varchar(200)',
        ];
        $map = [
            'TagID' => 'id',
            'Name' => 'identifier',
            'Active' => 'enabled',
        ];
        $query = $ex->dbImport()->table('PORT_ReactionType')->select('*');

        $ex->import('reactions', $query, $structure, $map);

        // Post Reactions
        $structure = [
            'id' => 'int',
            'post_id' => 'int',
            'user_id' => 'int',
            'reaction_id' => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
        $map = [
            'RecordID' => 'post_id',
            'UserID' => 'user_id',
            'TagID' => 'reaction_id',
            'DateInserted' => 'created_at',
        ];
        $query = $ex->dbImport()->table('PORT_UserTag')
            ->select(
                'RecordID',
                'UserID',
                'TagID',
                'DateInserted'
            )->where('RecordType', '=', 'Comment');

        // Get reactions for discussions (OPs).
        if ($this->getDiscussionBodyMode()) {
            // Get highest CommentID.
            $result = $ex->dbImport()
                ->table('PORT_Comment')
                ->select($ex->dbImport()->raw('max(CommentID) as LastCommentID'))
                ->first();

            /* @see Target\Flarum::comments() —  replicate our math in the post split */
            $discussionReactions = $ex->dbImport()->table('PORT_UserTag')
                ->select(
                    $ex->dbImport()->raw('(RecordID + ' . $result->LastCommentID . ') as RecordID'),
                    'UserID',
                    'TagID',
                    'DateInserted'
                )->where('RecordType', '=', 'Discussion');

            // Combine discussion reactions + comment reactions => post reactions.
            $query->union($discussionReactions);
        }

        $ex->import('post_reactions', $query, $structure, $map);
    }

    /**
     * Export PMs to fof/byobu format.
     *
     * @param ExportModel $ex
     */
    protected function privateMessages(ExportModel $ex)
    {
        // Get max IDs for offset.
        $MaxCommentID = $ex->dbImport()
            ->table('PORT_Comment')
            ->select($ex->dbImport()->raw('max(CommentID) as LastCommentID'))
            ->first()->LastCommentID;
        $MaxDiscussionID = $ex->dbImport()
            ->table('PORT_Discussion')
            ->select($ex->dbImport()->raw('max(DiscussionID) as LastDiscussionID'))
            ->first()->LastDiscussionID;

        // Messages — Comments
        $map = [
            'Body' => 'content',
            'InsertUserID' => 'user_id',
            'DateInserted' => 'created_at',
        ];
        $query = $ex->dbImport()->table('PORT_ConversationMessage')->select(
            $ex->dbImport()->raw('(MessageID + ' . $MaxCommentID . ') as id'),
            $ex->dbImport()->raw('(ConversationID + ' . $MaxDiscussionID . ') as discussion_id'),
            'Body',
            'InsertUserID',
            'DateInserted',
            $ex->dbImport()->raw('1 as is_private'),
        );

        $ex->import('posts', $query, [], $map); // Table already built; no structure.

        // Messages — Discussions
        $map = [
            'InsertUserID' => 'user_id',
            'DateInserted' => 'created_at',
        ];
        $query = $ex->dbImport()->table('PORT_Conversation')->select(
            $ex->dbImport()->raw('(ConversationID + ' . $MaxDiscussionID . ') as id'),
            'InsertUserID',
            'DateInserted',
            $ex->dbImport()->raw('Subject as title') // @todo excerpt the OP if no Subject exists
        );

        $ex->import('discussions', $query, [], $map); // Table already built; no structure.

        // Recipients
        $structure = [
            //'id' => 'int',
            'discussion_id' => 'int',
            'user_id' => 'int',
            //'group_id' => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
        $map = [
            'UserID' => 'user_id',
            'DateConversationUpdated' => 'updated_at',
        ];
        $query = $ex->dbImport()->table('PORT_UserConversation')->select(
            $ex->dbImport()->raw('(ConversationID + ' . $MaxDiscussionID . ') as discussion_id'),
            'UserID',
            'DateConversationUpdated'
        );

        $ex->import('recipients', $query, $structure, $map);
    }
}
