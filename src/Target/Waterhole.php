<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author Lincoln Russell, lincolnwebs.com
 * @author Toby Zerner, tobyzerner.com
 */

namespace Porter\Target;

use Porter\ExportModel;
use Porter\Formatter;
use Porter\Target;

class Waterhole extends Target
{
    public const SUPPORTED = [
        'name' => 'Waterhole',
        'prefix' => '',
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 'channels',
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 1,
            'PrivateMessages' => 0,
            'Attachments' => 0,
            'Bookmarks' => 0,
            'Badges' => 0,
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 0,
            'Reactions' => 0,
        ]
    ];

    protected const FLAGS = [
        'hasDiscussionBody' => true,
    ];

    /**
     * @var array Table structure for `comments`.
     */
    protected const DB_STRUCTURE_COMMENTS = [
        'id' => 'bigint',
        'post_id' => 'bigint',
        'parent_id' => 'bigint',
        'user_id' => 'bigint',
        'body' => 'mediumtext',
        'created_at' => 'timestamp',
        'edited_at' => 'timestamp',
        'reply_count' => 'int',
        'score' => 'int',
    ];

    /**
     * @var array Table structure for 'posts`.
     */
    protected const DB_STRUCTURE_POSTS = [
        'id' => 'bigint',
        'channel_id' => 'bigint',
        'user_id' => 'bigint',
        'title' => 'varchar(255)',
        'slug' => 'varchar(255)',
        'body' => 'mediumtext',
        'created_at' => 'timestamp',
        'edited_at' => 'timestamp',
        'last_activity_at' => 'timestamp',
        'comment_count' => 'int',
        'score' => 'int',
        'is_locked' => 'tinyint',
    ];

    /** @var int Offset for inserting OP content into the comments table. */
    protected int $postCommentOffset = 0;

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
    protected function getStructurePosts(ExportModel $ex)
    {
        $structure = self::DB_STRUCTURE_POSTS;
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
        $this->roles($ex); // 'Groups' in Waterhole
        $this->categories($ex); // 'Channels' in Waterhole

        // Singleton factory; big timing issue; depends on Users being done.
        // Formatter::instance($ex); // @todo Hook for pre-UGC import?

        $this->discussions($ex); // 'Posts' in Waterhole
        $this->comments($ex);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $structure = [
            'id' => 'bigint',
            'name' => 'varchar(255)',
            'email' => 'varchar(255)',
            'email_verified_at' => 'timestamp',
            'password' => 'varchar(255)',
            'avatar' => 'varchar(255)',
            'created_at' => 'timestamp',
            'last_seen_at' => 'timestamp',
        ];
        $map = [
            'UserID' => 'id',
            'Name' => 'name',
            'Email' => 'email',
            'Password' => 'password',
            'Photo' => 'avatar',
            'DateInserted' => 'created_at',
            'DateLastActive' => 'last_seen_at',
            'Confirmed' => 'email_verified_at',
        ];
        $filters = [
            'Name' => 'fixDuplicateDeletedNames',
            'Email' => 'fixNullEmails',
        ];
        $query = $ex->dbImport()->table('PORT_User')->select('*');

        $ex->import('users', $query, $structure, $map, $filters);
    }

    /**
     * Waterhole handles role assignment in a magic way.
     *
     * This compensates by shifting all RoleIDs +4, rendering any old 'Member' or 'Guest' role useless & deprecated.
     *
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $structure = [
            'id' => 'bigint',
            'name' => 'varchar(255)',
            'color' => 'varchar(255)',
            'icon' => 'varchar(255)',
            'is_public' => 'tinyint',
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
            $ex->dbImport()->raw("(RoleID + 4) as id"), // Flarum reserves 1-3 & uses 4 for mods by default.
            'Name as name',
            $ex->dbImport()->raw('0 as is_public')
        );

        $ex->import('groups', $query, $structure, $map);

        // User Role.
        $structure = [
            'user_id' => 'bigint',
            'group_id' => 'bigint',
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
            'id' => 'bigint',
            'name' => 'varchar(255)',
            'slug' => 'varchar(255)',
            'description' => 'text',
        ];
        $map = [
            'CategoryID' => 'id',
            'Name' => 'name',
            'UrlCode' => 'slug',
            'Description' => 'description',
        ];
        $query = $ex->dbImport()->table('PORT_Category')
            ->select(
                '*',
            )->where('CategoryID', '!=', -1); // Ignore Vanilla's root category.

        $ex->import('channels', $query, $structure, $map);
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $structure = $this->getStructurePosts($ex);
        $map = [
            'DiscussionID' => 'id',
            'CategoryID' => 'channel_id',
            'InsertUserID' => 'user_id',
            'Name' => 'title',
            'DateInserted' => 'created_at',
            'DateLastComment' => 'last_activity_at',
            'Closed' => 'is_locked',
            'Body' => 'body',
        ];
        $filters = [
            'slug' => 'createDiscussionSlugs',
        ];

        // CountComments needs to be double-mapped so it's included as an alias also.
        $query = $ex->dbImport()->table('PORT_Discussion')
            ->select(
                '*',
                $ex->dbImport()->raw('DiscussionID as slug'),
            );

        $ex->import('posts', $query, $structure, $map, $filters);
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $map = [
            'CommentID' => 'id',
            'DiscussionID' => 'post_id',
            'InsertUserID' => 'user_id',
            'DateInserted' => 'created_at',
            'DateUpdated' => 'edited_at',
            'Body' => 'body'
        ];
        $filters = [
            // 'Body' => 'filterFlarumContent',
        ];
        $query = $ex->dbImport()->table('PORT_Comment')
            ->select(
                'CommentID',
                'DiscussionID',
                'InsertUserID',
                'DateInserted',
                'DateUpdated',
                'Body',
                'Format',
            );

        $ex->import('comments', $query, self::DB_STRUCTURE_COMMENTS, $map, $filters);
    }
}
