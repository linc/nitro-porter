<?php

/**
 *
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
        //'best_answer_notified' => 'tinyint', // fof/best-answer
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
    public function validate(ExportModel $ex): void
    {
        $this->uniqueUserNames($ex);
        $this->uniqueUserEmails($ex);
    }

    /**
     * @param ExportModel $ex
     * @return array
     */
    protected function getStructureDiscussions(ExportModel $ex)
    {
        $structure = self::DB_STRUCTURE_DISCUSSIONS;
        // fof/gamification — no data, just prevent failure (no default values are set)
        if ($ex->targetExists('discussions', ['votes'])) {
            $structure['votes'] = 'int';
            $structure['hotness'] = 'double';
        }
        if ($ex->targetExists('discussions', ['view_count'])) {
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
     * @throws \Exception
     */
    public function uniqueUserNames(ExportModel $ex): void
    {
        $allowlist = [
            '[Deleted User]',
            '[DeletedUser]',
            '-Deleted-User-',
            '[Slettet bruker]', // Norwegian
            '[Utilisateur supprimé]', // French
        ]; // @see fixDuplicateDeletedNames()
        $dupes = array_diff($ex->findDuplicates('User', 'Name'), $allowlist);
        if (!empty($dupes)) {
            $ex->comment('DATA LOSS! Users skipped for duplicate user.name: ' . implode(', ', $dupes));
        }
    }

    /**
     * Flarum must have unique emails. Report users skipped (because of `insert ignore`).
     *
     * @param ExportModel $ex
     * @throws \Exception
     * @see uniqueUserNames
     *
     */
    public function uniqueUserEmails(ExportModel $ex): void
    {
        $dupes = $ex->findDuplicates('User', 'Email');
        if (!empty($dupes)) {
            $ex->comment('DATA LOSS! Users skipped for duplicate user.email: ' . implode(', ', $dupes));
        }
    }

    /**
     * Main import process.
     */
    public function run(ExportModel $ex): void
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
        $query = $ex->dbPorter()
            ->table('User')
            ->select()
            ->selectRaw('COALESCE(Confirmed, 0) as is_email_confirmed'); // Cannot be null.

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
        if (!$ex->portExists('UserRole')) {
            $ex->comment('Skipping import: Roles (Source lacks support)');
            $ex->importEmpty('groups', $structure);
            $ex->importEmpty('group_user', $structure);
            return;
        }

        // Delete orphaned user role associations (deleted users).
        $ex->pruneOrphanedRecords('UserRole', 'UserID', 'User', 'UserID');

        $query = $ex->dbPorter()
            ->table('Role')
            // Flarum reserves 1-3 & uses 4 for mods by default.
            ->selectRaw("(RoleID + 4) as id")
            // Singular vs plural is an uncommon feature; don't guess at it, just duplicate the Name.
            ->selectRaw('COALESCE(Name, CONCAT("role", RoleID)) as name_singular') // Cannot be null.
            ->selectRaw('COALESCE(Name, CONCAT("role", RoleID)) as name_plural') // Cannot be null.
            // Hiding roles is an uncommon feature; hide none.
            ->selectRaw('0 as is_hidden');

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
        $query = $ex->dbPorter()
            ->table('UserRole')
            ->select()
            ->selectRaw("(RoleID + 4) as RoleID"); // Match above offset

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
            'is_hidden' => 'tinyint',
            'is_restricted' => 'tinyint',
        ];
        $map = [
            'CategoryID' => 'id',
            'Name' => 'name',
            'Description' => 'description',
            'ParentCategoryID' => 'parent_id',
            'Sort' => 'position',
            'CountDiscussions' => 'discussion_count',
        ];
        $filters = [
            'CountDiscussions' => 'emptyToZero',
        ];
        $query = $ex->dbPorter()
            ->table('Category')
            ->select()
            ->selectRaw('COALESCE(Name, CONCAT("category", CategoryID)) as name') // Cannot be null.
            ->selectRaw('COALESCE(UrlCode, CategoryID) as slug') // Cannot be null.
            ->selectRaw("if(ParentCategoryID = -1, null, ParentCategoryID) as ParentCategoryID")
            ->selectRaw("0 as is_hidden")
            ->selectRaw("0 as is_restricted")
            ->where('CategoryID', '!=', -1); // Ignore Vanilla's root category.

        $ex->import('tags', $query, $structure, $map, $filters);
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
            'CountComments' => 'comment_count',
            'Announce' => 'is_sticky', // Flarum doesn't mind if this is '2' so straight map it.
            'Closed' => 'is_locked',
        ];
        $filters = [
            'slug' => 'createDiscussionSlugs', // 'DiscussionID as slug' (below).
            'Announce' => 'emptyToZero',
            'Closed' => 'emptyToZero',
        ];

        // flarumite/simple-discussion-views
        if ($ex->targetExists('discussions', ['view_count'])) {
            $structure['view_count'] = 'int';
            $map['CountViews'] = 'view_count';
            $filters['CountViews'] = 'emptyToZero';
        }

        // CountComments needs to be double-mapped so it's included as an alias also.
        $query = $ex->dbPorter()
            ->table('Discussion')
            ->select()
            ->selectRaw('COALESCE(CountComments, 0) as post_number_index')
            ->selectRaw('DiscussionID as slug')
            ->selectRaw('CountComments as last_post_number')
            ->selectRaw('0 as votes')
            ->selectRaw('0 as hotness')
            ->selectRaw('1 as best_answer_notified');

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
        $query = $ex->dbPorter()->table('Discussion')->select(['DiscussionID', 'CategoryID']);

        $ex->import('discussion_tag', $query, $structure, $map, $filters);
    }

    /**
     * @param ExportModel $ex
     */
    protected function bookmarks(ExportModel $ex): void
    {
        // Verify support.
        if (!$ex->portExists('UserDiscussion')) {
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
        $query = $ex->dbPorter()
            ->table('UserDiscussion')
            ->select()
            ->selectRaw("if (Bookmarked > 0, 'follow', null) as subscription")
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
        $query = $ex->dbPorter()
            ->table('Comment')
            // SELECT ORDER IS SENSITIVE DUE TO THE UNION() BELOW.
            ->select([
                'DiscussionID',
                'InsertUserID',
                'DateInserted',
                'DateUpdated',
                'UpdateUserID',
                'Body',
                'Format'])
            ->selectRaw('CommentID as CommentID')
            ->selectRaw('"comment" as type')
            ->selectRaw('null as number');

        // Extract OP from the discussion.
        if ($this->getDiscussionBodyMode()) {
            // Get highest CommentID.
            $result = $ex->dbPorter()
                ->table('Comment')
                ->selectRaw('max(CommentID) as LastCommentID')
                ->first();

            // Save value for other associations (e.g. attachments).
            $this->discussionPostOffset = $result->LastCommentID ?? 0;

            // Use DiscussionID but fast-forward it past highest CommentID to insure it's unique.
            $discussions = $ex->dbPorter()->table('Discussion')
                ->select([
                    'DiscussionID',
                    'InsertUserID',
                    'DateInserted',
                    'DateUpdated',
                    'UpdateUserID',
                    'Body',
                    'Format'])
                ->selectRaw('(DiscussionID + ' . $this->discussionPostOffset . ') as CommentID')
                ->selectRaw('"comment" as type')
                ->selectRaw('null as number');

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
        if (!$ex->portExists('Media')) {
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
            'Size' => 'size',
        ];
        $query = $ex->dbPorter()->table('Media')
            ->select()
            ->selectRaw('0 as discussion_id')
            ->selectRaw('trim(leading "/" from Path) as path')
            // @todo Not a URL yet.
            ->selectRaw('concat("/assets/files/", trim(leading "/" from Path)) as url')
            // Untangle the Media.ForeignID & Media.ForeignTable [comment, discussion, message]
            ->selectRaw("case
                when ForeignID is null then 0
                when ForeignTable = 'comment' then ForeignID
                when ForeignTable = 'Comment' then ForeignID
                when ForeignTable = 'discussion' then ifnull((ForeignID + " . $this->discussionPostOffset . "), 0)
                when ForeignTable = 'embed' then 0
                when ForeignTable = 'message' then ifnull((ForeignID + " . $this->messagePostOffset . "), 0)
                end as post_id")
            ->selectRaw('"local" as upload_method')
            // MIME type cannot be null, so default to "application/octet-stream" as most generic default.
            ->selectRaw('COALESCE(Type, "application/octet-stream") as type')
            // @see packages/upload/src/Providers/DownloadProvider.php
            ->selectRaw("case
                when Type like 'image/%' then 'image-preview'
                else 'file'
                end as tag");

        $ex->import('fof_upload_files', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function badges(ExportModel $ex): void
    {
        // Verify support.
        if (!$ex->portExists('Badge')) {
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
        $query = $ex->dbPorter()->table('Badge')
            ->select()
            ->selectRaw('1 as badge_category_id');

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
        $query = $ex->dbPorter()->table('UserBadge')->select('*');

        $ex->import('badge_user', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function polls(ExportModel $ex): void
    {
        // Verify support.
        if (!$ex->portExists('Poll')) {
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
        $query = $ex->dbPorter()->table('Poll')
            ->select('*')
            ->select('DateInserted as end_date')
                // Whether its public or anonymous are inverse conditions, so flip the value.
            ->selectRaw('if(Anonymous>0, 0, 1) as public_poll');

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
        $query = $ex->dbPorter()->table('PollOption')->select('*');

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
        $query = $ex->dbPorter()->table('PollVote')
            ->leftJoin('PollOption', 'PollVote.PollOptionID', '=', 'PollOption.PollOptionID')
            ->select(['PollVote.*',
                'PollOption.PollID as poll_id',
                'PollOption.DateInserted as created_at', // Total hack for approximate vote dates.
                'PollOption.DateUpdated as updated_at']);

        $ex->import('poll_votes', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    public function reactions(ExportModel $ex): void
    {
        // Verify support.
        if (!$ex->portExists('ReactionType')) {
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
        $query = $ex->dbPorter()->table('ReactionType')
            // @todo Setting type='emoji' is a kludge since it won't render Vanilla defaults that way.
            ->select('*')
            ->selectRaw('"emoji" as type');

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
        // SELECT ORDER IS SENSITIVE DUE TO THE UNION() BELOW.
        $query = $ex->dbPorter()->table('UserTag')
            ->select(['UserID', 'TagID'])
            ->selectRaw('RecordID as RecordID')
            ->selectRaw('TIMESTAMP(DateInserted) as DateInserted')
            ->where('RecordType', '=', 'Comment')
            ->where('UserID', '>', 0);

        // Get reactions for discussions (OPs).
        if ($this->getDiscussionBodyMode()) {
            // Get highest CommentID.
            $result = $ex->dbPorter()
                ->table('Comment')
                ->selectRaw('max(CommentID) as LastCommentID')
                ->first();
            $lastCommentID = $result->LastCommentID ?? 0;

            /* @see Target\Flarum::comments() —  replicate our math in the post split */
            $discussionReactions = $ex->dbPorter()->table('UserTag')
                ->select(['UserID', 'TagID'])
                ->selectRaw('(RecordID + ' . $lastCommentID . ') as RecordID')
                ->selectRaw('TIMESTAMP(DateInserted) as DateInserted')
                ->where('RecordType', '=', 'Discussion')
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
    protected function privateMessages(ExportModel $ex): void
    {
        // Verify source support.
        if (!$ex->portExists('Conversation')) {
            $ex->comment('Skipping import: Private messages (Source lacks support)');
            return;
        }

        // Verify target support.
        if (!$ex->targetExists('recipients')) {
            $ex->comment('Skipping import: Private messages (Target lacks support - Enable the plugin first)');
            return;
        }

        // Messages — Discussions
        $MaxDiscussionID = $this->messageDiscussionOffset = $this->getMaxValue('id', 'discussions', $ex);
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
        if ($ex->targetExists('discussions', ['votes'])) {
            $structure['votes'] = 'int';
        }

        $query = $ex->dbPorter()->table('Conversation')
            ->select(['InsertUserID', 'DateInserted'])
            ->selectRaw('(ConversationID + ' . $MaxDiscussionID . ') as id')
            ->selectRaw('DateInserted as last_posted_at') // @todo Orders old PMs by OP instead of last comment.
            ->selectRaw('1 as is_private')
            ->selectRaw('0 as votes') // Hedge against fof/gamification
            ->selectRaw('0 as hotness') // Hedge against fof/gamification
            ->selectRaw('0 as view_count')
            ->selectRaw('(ConversationID + ' . $MaxDiscussionID . ') as slug')
            // Use a numbered title "Private discussion 1234" if there's no Subject line.
            ->selectRaw('ifnull(Subject,
                concat("Private discussion ", (ConversationID + ' . $MaxDiscussionID . '))) as title');

        $ex->import('discussions', $query, $structure, $map, $filters);

        // Messages — Comments
        $MaxCommentID = $this->messagePostOffset = $this->getMaxValue('id', 'posts', $ex);
        $ex->comment('Posts offset for PMs is ' . $MaxCommentID);
        $map = [
            'Body' => 'content',
            'InsertUserID' => 'user_id',
            'DateInserted' => 'created_at',
        ];
        $filters = [
            'Body' => 'filterFlarumContent',
        ];
        $query = $ex->dbPorter()->table('ConversationMessage')
            ->select(['Body', 'Format', 'InsertUserID', 'DateInserted'])
            ->selectRaw('(MessageID + ' . $MaxCommentID . ') as id')
            ->selectRaw('(ConversationID + ' . $MaxDiscussionID . ') as discussion_id')
            ->selectRaw('1 as is_private')
            ->selectRaw('"comment" as type');

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
        $query = $ex->dbPorter()->table('UserConversation')
            ->select(['UserID', 'DateConversationUpdated'])
            ->selectRaw('(ConversationID + ' . $MaxDiscussionID . ') as discussion_id');

        $ex->import('recipients', $query, $structure, $map);
    }
}
