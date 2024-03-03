<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Target;

use Porter\ExportModel;
use Porter\Formatter;
use Porter\Target;

/**
 * You'll notice a seemingly random mix of datetime and timestamp in the Flarum database.
 *
 * Synch0, 2022-08-01:
 * > Back in 2014-16, the default was datetime, but then Laravel switched to timestamp by default.
 */
class Flarum extends Target
{
    public const SUPPORTED = [
        'name' => 'Flarum',
        'prefix' => 'FLA_',
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 'tags',
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 'fof/polls',
            'Roles' => 1,
            'Avatars' => 1,
            'PrivateMessages' => 'fof/byobu',
            'Attachments' => 'fof/uploads',
            'Bookmarks' => 'subscriptions',
            'Badges' => 'v17development/flarum-user-badges',
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 0,
            'Reactions' => 'fof/reactions',
        ]
    ];

    protected const FLAGS = [
        'hasDiscussionBody' => false,
    ];

    /**
     * @var array Table structure for `posts`.
     * @see \Porter\Postscript\Flarum::numberPosts() for 'keys' requirement.
     */
    protected const DB_STRUCTURE_POSTS = [
        'id' => 'int',
        'discussion_id' => 'int',
        'user_id' => 'int',
        'created_at' => 'datetime',
        'edited_at' => 'datetime',
        'edited_user_id' => 'int',
        'type' => 'varchar(100)',
        'content' => 'longText',
        'number' => 'int',
        'keys' => [
            'FLA_posts_discussion_id_number_unique' => [
                'type' => 'unique',
                'columns' => ['discussion_id', 'number'],
            ],
            'FLA_posts_id_primary' => [
                'type' => 'primary',
                'columns' => ['id'],
            ]
        ],
    ];

    /**
     * @var array Table structure for 'discussions`.
     * @see \Porter\Postscript\Flarum::numberPosts() for 'keys' requirement.
     */
    protected const DB_STRUCTURE_DISCUSSIONS = [
        'id' => 'int',
        'user_id' => 'int',
        'title' => 'varchar(200)',
        'slug' => 'varchar(200)',
        'tag_id' => 'int',
        'created_at' => 'datetime',
        'first_post_id' => 'int',
        'last_post_id' => 'int',
        'last_posted_at' => 'datetime',
        'last_posted_user_id' => 'int',
        'post_number_index' => 'int',
        'is_private' => 'tinyint', // fof/byobu (PMs)
        'is_sticky' => 'tinyint', // flarum/sticky
        'is_locked' => 'tinyint', // flarum/lock
        //'votes' => 'int', // fof/polls
        //'hotness' => 'double', // fof/gamification
        //'view_count' => 'int', // flarumite/simple-discussion-views
        'keys' => [
            'FLA_discussions_id_primary' => [
                'type' => 'primary',
                'columns' => ['id'],
            ]
        ],
    ];

    /** @var int Offset for inserting OP content into the posts table. */
    protected int $discussionPostOffset = 0;

    /** @var int Offset for inserting PMs into posts table. */
    protected int $messagePostOffset = 0;

    /** @var int  Offset for inserting PMs into discussions table. */
    protected int $messageDiscussionOffset = 0;

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
     * @param ExportModel $ex
     * @return string[]
     */
    protected function getStructureDiscussions(ExportModel $ex)
    {
        $structure = self::DB_STRUCTURE_DISCUSSIONS;
        // fof/gamification — no data, just prevent failure (no default values are set)
        if ($ex->targetExists($ex->tarPrefix . 'discussions', ['votes'])) {
            $structure['votes'] = 'int';
            $structure['hotness'] = 'double';
        }
        if ($ex->targetExists($ex->tarPrefix . 'discussions', ['view_count'])) {
            $structure['view_count'] = 'int'; // flarumite/simple-discussion-views
        }
        return $structure;
    }

    /**
     * Flarum must have unique usernames. Report users skipped (because of `insert ignore`).
     *
     * Unsure this could get automated fix. You'd have to determine which has/have data attached and possibly merge.
     * You'd also need more data from findDuplicates, especially the IDs.
     * Folks are just gonna need to manually edit their existing forum data for now to rectify dupe issues.
     *
     * @param ExportModel $ex
     */
    public function uniqueUserNames(ExportModel $ex)
    {
        $allowlist = [
            '[Deleted User]',
            '[DeletedUser]',
            '-Deleted-User-',
            '[Slettet bruker]', // Norwegian
            '[Utilisateur supprimé]', // French
        ]; // @see fixDuplicateDeletedNames()
        $dupes = array_diff($ex->findDuplicates('PORT_User', 'Name'), $allowlist);
        if (!empty($dupes)) {
            $ex->comment('DATA LOSS! Users skipped for duplicate user.name: ' . implode(', ', $dupes));
        }
    }

    /**
     * Flarum must have unique emails. Report users skipped (because of `insert ignore`).
     *
     * @see uniqueUserNames
     *
     * @param ExportModel $ex
     */
    public function uniqueUserEmails(ExportModel $ex)
    {
        $dupes = $ex->findDuplicates('PORT_User', 'Email');
        if (!empty($dupes)) {
            $ex->comment('DATA LOSS! Users skipped for duplicate user.email: ' . implode(', ', $dupes));
        }
    }

    /**
     * Main import process.
     */
    public function run(ExportModel $ex)
    {
        // Ignore constraints on tables that block import.
        $ex->ignoreDuplicates('users');

        $this->users($ex);
        $this->roles($ex); // 'Groups' in Flarum
        $this->categories($ex); // 'Tags' in Flarum

        // Singleton factory; big timing issue; depends on Users being done.
        Formatter::instance($ex); // @todo Hook for pre-UGC import?

        $this->discussions($ex);
        $this->bookmarks($ex); // Requires addon `flarum/subscriptions`
        $this->comments($ex); // 'Posts' in Flarum

        $this->badges($ex); // Requires addon `17development/flarum-user-badges`
        $this->polls($ex); // Requires addon `fof/pollsx`
        $this->reactions($ex); // Requires addon `fof/reactions`

        $this->privateMessages($ex); // Requires addon `fof/byobu`

        $this->attachments($ex); // Requires discussions, comments, and PMs have imported.
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
            'is_email_confirmed' => 'tinyint',
            'password' => 'varchar(100)',
            'avatar_url' => 'varchar(100)',
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'discussion_count' => 'int',
            'comment_count' => 'int',
        ];
        $map = [
            'UserID' => 'id',
            'Name' => 'username',
            'Email' => 'email',
            'Password' => 'password',
            'Photo' => 'avatar_url',
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
     * Flarum handles role assignment in a magic way.
     *
     * This compensates by shifting all RoleIDs +4, rendering any old 'Member' or 'Guest' role useless & deprecated.
     * @see https://docs.flarum.org/extend/permissions/
     *
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $structure = [
            'id' => 'int',
            'name_singular' => 'varchar(100)',
            'name_plural' => 'varchar(100)',
            'color' => 'varchar(20)',
            'icon' => 'varchar(100)',
            'is_hidden' => 'tinyint',
        ];
        $map = [];

        // Verify support.
        if (!$ex->targetExists('PORT_UserRole')) {
            $ex->comment('Skipping import: Roles (Source lacks support)');
            $ex->importEmpty('groups', $structure);
            $ex->importEmpty('group_user', $structure);
            return;
        }

        // Delete orphaned user role associations (deleted users).
        $ex->pruneOrphanedRecords('PORT_UserRole', 'UserID', 'PORT_User', 'UserID');

        $query = $ex->dbImport()->table('PORT_Role')->select(
            // Flarum reserves 1-3 & uses 4 for mods by default.
            $ex->dbImport()->raw("(RoleID + 4) as id"),
            // Singular vs plural is an uncommon feature; don't guess at it, just duplicate the Name.
            $ex->dbImport()->raw('COALESCE(Name, CONCAT("role", RoleID)) as name_singular'), // Cannot be null.
            $ex->dbImport()->raw('COALESCE(Name, CONCAT("role", RoleID)) as name_plural'), // Cannot be null.
            // Hiding roles is an uncommon feature; hide none.
            $ex->dbImport()->raw('0 as is_hidden')
        );

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
        $query = $ex->dbImport()->table('PORT_UserRole')->select(
            '*',
            $ex->dbImport()->raw("(RoleID + 4) as RoleID") // Match above offset
        );

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
        $query = $ex->dbImport()->table('PORT_Category')
            ->select(
                '*',
                $ex->dbImport()->raw('COALESCE(Name, CONCAT("category", id)) as name'), // Cannot be null.
                $ex->dbImport()->raw("if(ParentCategoryID = -1, null, ParentCategoryID) as ParentCategoryID"),
                $ex->dbImport()->raw("0 as is_hidden"),
                $ex->dbImport()->raw("0 as is_restricted")
            )->where('CategoryID', '!=', -1); // Ignore Vanilla's root category.

        $ex->import('tags', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $structure = $this->getStructureDiscussions($ex);
        $map = [
            'DiscussionID' => 'id',
            'InsertUserID' => 'user_id',
            'Name' => 'title',
            'DateInserted' => 'created_at',
            'FirstCommentID' => 'first_post_id',
            'LastCommentID' => 'last_post_id',
            'DateLastComment' => 'last_posted_at',
            'LastCommentUserID' => 'last_posted_user_id',
            'CountComments' => 'last_post_number',
            'Announce' => 'is_sticky', // Flarum doesn't mind if this is '2' so straight map it.
            'Closed' => 'is_locked',
        ];
        $filters = [
            'slug' => 'createDiscussionSlugs',
        ];

        // flarumite/simple-discussion-views
        if ($ex->targetExists($ex->tarPrefix . 'discussions', ['view_count'])) {
            $structure['view_count'] = 'int';
            $map['CountViews'] = 'view_count';
        }

        // CountComments needs to be double-mapped so it's included as an alias also.
        $query = $ex->dbImport()->table('PORT_Discussion')
            ->select(
                '*',
                $ex->dbImport()->raw('CountComments as post_number_index'),
                $ex->dbImport()->raw('DiscussionID as slug'),
                $ex->dbImport()->raw('0 as votes'),
                $ex->dbImport()->raw('0 as hotness')
            );

        $ex->import('discussions', $query, $structure, $map, $filters);

        // Flarum has a separate pivot table for discussion tags.
        $structure = [
            'discussion_id' => 'int',
            'tag_id' => 'int',
        ];
        $map = [
            'DiscussionID' => 'discussion_id',
            'CategoryID' => 'tag_id',
        ];
        $query = $ex->dbImport()->table('PORT_Discussion')->select(['DiscussionID', 'CategoryID']);

        $ex->import('discussion_tag', $query, $structure, $map, $filters);
    }

    /**
     * @param ExportModel $ex
     */
    protected function bookmarks(ExportModel $ex): void
    {
        // Verify support.
        if (!$ex->targetExists('PORT_UserDiscussion')) {
            $ex->comment('Skipping import: Bookmarks (Source lacks support)');
            return;
        }

        $structure = [
            'discussion_id' => 'int',
            'user_id' => 'int',
            'last_read_at' => 'datetime',
            'subscription' => [null, 'follow', 'ignore'],
            'last_read_post_number' => 'int',
            'keys' => [
                'FLA_discussion_user_discussion_id_foreign' => [
                    'type' => 'index',
                    'columns' => ['discussion_id'],
                ],
            ],
        ];
        $map = [
            'DiscussionID' => 'discussion_id',
            'UserID' => 'user_id',
            'DateLastViewed' => 'last_read_at',
        ];
        $query = $ex->dbImport()->table('PORT_UserDiscussion')
            ->select('*', ($ex->dbImport()->raw("if (Bookmarked > 0, 'follow', null) as subscription")))
            ->where('UserID', '>', 0); // Vanilla can have zeroes here, can't remember why.

        $ex->import('discussion_user', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
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
                $ex->dbImport()->raw('"comment" as type'),
                $ex->dbImport()->raw('null as number')
            );

        // Extract OP from the discussion.
        if ($this->getDiscussionBodyMode()) {
            // Get highest CommentID.
            $result = $ex->dbImport()
                ->table('PORT_Comment')
                ->select($ex->dbImport()->raw('max(CommentID) as LastCommentID'))
                ->first();

            // Save value for other associations (e.g. attachments).
            $this->discussionPostOffset = $result->LastCommentID;

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
                    $ex->dbImport()->raw('"comment" as type'),
                    $ex->dbImport()->raw('null as number')
                );

            // Combine discussions.body with the comments to get all posts.
            $query->union($discussions);
        }

        $ex->import('posts', $query, self::DB_STRUCTURE_POSTS, $map, $filters);
    }

    /**
     * Currently discards thumbnails because Flarum's extension doesn't have any.
     *
     * @todo Support for `fof_upload_files.discussion_id` field, likely in Postscript (it's derived data).
     *
     * @param ExportModel $ex
     */
    protected function attachments(ExportModel $ex): void
    {
        // Verify support.
        if (!$ex->targetExists('PORT_Media')) {
            $ex->comment('Skipping import: Attachments (Source lacks support)');
            return;
        }

        $structure = [
            'id' => 'int',
            'actor_id' => 'int',
            'discussion_id' => 'int',
            'post_id' => 'int',
            'base_name' => 'varchar(255)',
            'path' => 'varchar(255)', // from /forumroot/assets/files
            'url' => 'varchar(255)',
            'type' => 'varchar(255)', // MIME
            'size'  => 'int', // bytes
            'created_at' => 'datetime',
            'upload_method' => 'varchar(255)', // Probably just 'local'
            'tag' => 'varchar(255)', // Required; generates preview in Profile -> "My Media"
        ];
        $map = [
            'MediaID' => 'id',
            'Name' => 'base_name',
            'InsertUserID' => 'actor_id',
            'DateInserted' => 'created_at',
            'Type' => 'type',
            'Size' => 'size',
        ];
        $query = $ex->dbImport()->table('PORT_Media')->select(
            '*',
            $ex->dbImport()->raw('0 as discussion_id'),
            $ex->dbImport()->raw('trim(leading "/" from Path) as path'),
            // @todo Not a URL yet.
            $ex->dbImport()->raw('concat("/assets/files/", trim(leading "/" from Path)) as url'),
            // Untangle the Media.ForeignID & Media.ForeignTable [comment, discussion, message]
            $ex->dbImport()->raw("case
                when ForeignID is null then 0
                when ForeignTable = 'comment' then ForeignID
                when ForeignTable = 'Comment' then ForeignID
                when ForeignTable = 'discussion' then ifnull((ForeignID + " . $this->discussionPostOffset . "), 0)
                when ForeignTable = 'embed' then 0
                when ForeignTable = 'message' then ifnull((ForeignID + " . $this->messagePostOffset . "), 0)
                end as post_id"),
            $ex->dbImport()->raw('"local" as upload_method'),
            // @see packages/upload/src/Providers/DownloadProvider.php
            $ex->dbImport()->raw("case
                when Type like 'image/%' then 'image-preview'
                else 'file'
                end as tag")
        );

        $ex->import('fof_upload_files', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function badges(ExportModel $ex): void
    {
        // Verify support.
        if (!$ex->targetExists('PORT_Badge')) {
            $ex->comment('Skipping import: Badges (Source lacks support)');
            return;
        }

        // Badge Categories
        // One category is added in postscript.

        // Badges
        $structure = [
            'id' => 'int',
            'name' => 'varchar(200)',
            'image' => 'text',
            'description' => 'text',
            'badge_category_id' => 'int',
            'points' => 'int',
            'created_at' => 'datetime',
            'is_visible' => 'tinyint',
        ];
        $map = [
            'Name' => 'name',
            'BadgeID' => 'id',
            'Body' => 'description',
            'Photo' => 'image',
            'Points' => 'points',
            'InsertUserID' => 'user_id',
            'DateInserted' => 'created_at',
            'DateLastViewed' => 'last_read_at',
            'Visible' => 'is_visible',
        ];
        $query = $ex->dbImport()->table('PORT_Badge')
            ->select(
                '*',
                $ex->dbImport()->raw('1 as badge_category_id')
            );

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
        // Verify support.
        if (!$ex->targetExists('PORT_Poll')) {
            $ex->comment('Skipping import: Polls (Source lacks support)');
            return;
        }

        // Polls
        $structure = [
            'id' => 'int',
            'question' => 'varchar(200)',
            'discussion_id' => 'int',
            'user_id' => 'int',
            'public_poll' => 'tinyint', // Map to "Anonymous" somehow?
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
        $query = $ex->dbImport()->table('PORT_Poll')
            ->select(
                '*',
                'DateInserted as end_date',
                // Whether its public or anonymous are inverse conditions, so flip the value.
                $ex->dbImport()->raw('if(Anonymous>0, 0, 1) as public_poll')
            );

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
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ];
        $map = [
            'PollOptionID' => 'option_id',
            'UserID' => 'user_id',
        ];
        $query = $ex->dbImport()->table('PORT_PollVote')
            ->leftJoin('PORT_PollOption', 'PORT_PollVote.PollOptionID', '=', 'PORT_PollOption.PollOptionID')
            ->select(
                'PORT_PollVote.*',
                'PORT_PollOption.PollID as poll_id',
                'PORT_PollOption.DateInserted as created_at', // Total hack for approximate vote dates.
                'PORT_PollOption.DateUpdated as updated_at',
            );

        $ex->import('poll_votes', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    public function reactions(ExportModel $ex)
    {
        // Verify support.
        if (!$ex->targetExists('PORT_ReactionType')) {
            $ex->comment('Skipping import: Reactions (Source lacks support)');
            return;
        }

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
        $query = $ex->dbImport()->table('PORT_ReactionType')
            // @todo Setting type='emoji' is a kludge since it won't render Vanilla defaults that way.
            ->select('*', $ex->dbImport()->raw('"emoji" as type'));

        $ex->import('reactions', $query, $structure, $map);

        // Post Reactions
        $structure = [
            'id' => 'int',
            'post_id' => 'int',
            'user_id' => 'int',
            'reaction_id' => 'int',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
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
                $ex->dbImport()->raw('TIMESTAMP(DateInserted) as DateInserted'),
            )->where('RecordType', '=', 'Comment')
            ->where('UserID', '>', 0);

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
                    $ex->dbImport()->raw('TIMESTAMP(DateInserted) as DateInserted'),
                )->where('RecordType', '=', 'Discussion')
                ->where('UserID', '>', 0);

            // Combine discussion reactions + comment reactions => post reactions.
            $query->union($discussionReactions);
        }

        $ex->import('post_reactions', $query, $structure, $map);
    }

    /**
     * Export PMs to fof/byobu format, which uses the `posts` & `discussions` tables.
     *
     * @param ExportModel $ex
     */
    protected function privateMessages(ExportModel $ex)
    {
        // Verify support.
        if (!$ex->targetExists('PORT_Conversation')) {
            $ex->comment('Skipping import: Private messages (Source lacks support)');
            return;
        }

        // Messages — Discussions
        $MaxDiscussionID = $this->messageDiscussionOffset = $this->getMaxDiscussionID($ex);
        $ex->comment('Discussions offset for PMs is ' . $MaxDiscussionID);
        $structure = $this->getStructureDiscussions($ex);
        $map = [
            'InsertUserID' => 'user_id',
            'DateInserted' => 'created_at',
        ];
        $filters = [
            'slug' => 'createDiscussionSlugs',
        ];

        // fof/gamification — no data, just prevent failure (no default value is set)
        if ($ex->targetExists($ex->tarPrefix . 'discussions', ['votes'])) {
            $structure['votes'] = 'int';
        }

        $query = $ex->dbImport()->table('PORT_Conversation')->select(
            $ex->dbImport()->raw('(ConversationID + ' . $MaxDiscussionID . ') as id'),
            'InsertUserID',
            'DateInserted',
            'DateInserted as last_posted_at', // @todo Orders old PMs by OP instead of last comment.
            $ex->dbImport()->raw('1 as is_private'),
            $ex->dbImport()->raw('0 as votes'), // Hedge against fof/gamification
            $ex->dbImport()->raw('0 as hotness'), // Hedge against fof/gamification
            $ex->dbImport()->raw('0 as view_count'),
            $ex->dbImport()->raw('(ConversationID + ' . $MaxDiscussionID . ') as slug'),
            // Use a numbered title "Private discussion 1234" if there's no Subject line.
            $ex->dbImport()->raw('ifnull(Subject,
                concat("Private discussion ", (ConversationID + ' . $MaxDiscussionID . '))) as title')
        );

        $ex->import('discussions', $query, $structure, $map, $filters);

        // Messages — Comments
        $MaxCommentID = $this->messagePostOffset = $this->getMaxCommentID($ex);
        $ex->comment('Posts offset for PMs is ' . $MaxCommentID);
        $map = [
            'Body' => 'content',
            'InsertUserID' => 'user_id',
            'DateInserted' => 'created_at',
        ];
        $filters = [
            'Body' => 'filterFlarumContent',
        ];
        $query = $ex->dbImport()->table('PORT_ConversationMessage')->select(
            $ex->dbImport()->raw('(MessageID + ' . $MaxCommentID . ') as id'),
            $ex->dbImport()->raw('(ConversationID + ' . $MaxDiscussionID . ') as discussion_id'),
            'Body',
            'Format',
            'InsertUserID',
            'DateInserted',
            $ex->dbImport()->raw('1 as is_private'),
            $ex->dbImport()->raw('"comment" as type'),
        );

        $ex->import('posts', $query, self::DB_STRUCTURE_POSTS, $map, $filters);

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

    /**
     * Get current max id from `posts` table.
     *
     * Cannot use intermediary (PORT_) tables because we may have added posts elsewhere.
     *
     * @param ExportModel $ex
     * @return int
     */
    protected function getMaxCommentID(ExportModel $ex)
    {
        $MaxComment = $ex->dbImport()->table($ex->tarPrefix . 'posts')
            ->where('id', $ex->dbImport()->raw("(select max(`id`) from " . $ex->tarPrefix . 'posts' . ")"))
            ->get()->pluck('id');
        return $MaxComment[0] ?? 0;
    }

    /**
     * Get current max id from `discussions` table.
     *
     * @param ExportModel $ex
     * @return int
     */
    protected function getMaxDiscussionID(ExportModel $ex)
    {
        $MaxDiscussion = $ex->dbImport()->table($ex->tarPrefix . 'discussions')
            ->where('id', $ex->dbImport()->raw("(select max(`id`) from " . $ex->tarPrefix . 'discussions' . ")"))
            ->get()->pluck('id');
        return $MaxDiscussion[0] ?? 0;
    }
}
