<?php

namespace Porter\Postscript;

use Porter\ExportModel;
use Porter\Postscript;

class Flarum extends Postscript
{
    /** @var string[] Database structure for the table post_mentions_user. */
    public const DB_STRUCTURE_POST_MENTIONS_USER = [
        'post_id' => 'int',
        'mentions_user_id' => 'int',
    ];

    /**
     * Main process.
     *
     * @param ExportModel $ex
     */
    public function run(ExportModel $ex)
    {
        $this->userMentions($ex);
        $this->postData($ex);
        $this->defaultGroups($ex);
        $this->promoteAdmin($ex);
    }

    /**
     * Find mentions in posts and record to database table.
     */
    protected function userMentions(ExportModel $ex)
    {
        // Start timer.
        $start = microtime(true);
        $rows = 0;

        // Prepare mentions table.
        $this->storage->prepare('post_mentions_user', self::DB_STRUCTURE_POST_MENTIONS_USER);
        $ex->ignoreDuplicates('post_mentions_user'); // Primary key forbids more than 1 record per user/post.

        // Get post data.
        $posts = $this->connection->newConnection()
            ->table($ex->tarPrefix . 'posts')
            ->select(['id', 'discussion_id', 'content']);
        $memory = memory_get_usage();
        // Find & record mentions in batches.
        foreach ($posts->cursor() as $post) {
            // Find converted mentions and connect to userID.
            $mentions = [];
            preg_match_all(
                '/<USERMENTION .*id="(?<userids>[0-9]*)".*\/USERMENTION>/U',
                $post->content,
                $mentions
            );
            foreach ($mentions['userids'] as $userid) {
                // There can be multiple userids per post.
                $this->storage->stream([
                    'post_id' => $post->id,
                    'mentions_user_id' => (int)$userid
                ], self::DB_STRUCTURE_POST_MENTIONS_USER);
                $rows++;
            }
        }

        // Insert remaining mentions.
        $this->storage->endStream();

        // Report.
        $ex->reportStorage('build', 'mentions', microtime(true) - $start, $rows, $memory);
    }

    /**
     * Calculate post numbers for imported posts.
     *
     * Numbers are sequentially incremented chronologically per discussion, not an ID.
     *
     * @param ExportModel $ex
     */
    protected function postData(ExportModel $ex)
    {
        // Start timer.
        $start = microtime(true);
        $rows = 0;

        // Calculate & set posts.number.
        $db = $this->connection->newConnection();
        // Get only discussions with comments.
        $posts = $db->table($ex->tarPrefix . 'posts')
            ->distinct()
            ->get('discussion_id');
        $memory = memory_get_usage();
        // Update posts 2+ with their number, per discussion.
        foreach ($posts as $post) {
            $db->statement("set @num := 0");
            $count = $db->affectingStatement("update `" . $ex->tarPrefix . "posts`
                    set `number` = (@num := @num + 1)
                    where `discussion_id` = " . $post->discussion_id . "
                    order by `created_at` asc");
            $rows += $count;
        }

        // Report.
        $ex->reportStorage('build', 'post numbers', microtime(true) - $start, $rows, $memory);
    }

    /**
     * Recreate the default groups (1 = Admins, 2 = Guests, 3 = Members).
     *
     * @param ExportModel $ex
     */
    protected function defaultGroups(ExportModel $ex)
    {
        $db = $this->connection->newConnection();
        $db->table($ex->tarPrefix . 'groups')
            ->insert([
                ['id' => 1, 'name_singular' => 'Admin', 'name_plural' => 'Admins'],
                ['id' => 2, 'name_singular' => 'Guest', 'name_plural' => 'Guests'],
                ['id' => 3, 'name_singular' => 'Member', 'name_plural' => 'Members'],
                // Not strictly necessary, just safer because Mod-level permissions may be in `group_user` already.
                ['id' => 4, 'name_singular' => 'Mod', 'name_plural' => 'Mods'],
            ]);
    }

    /**
     * Promote the superadmin to the Flarum admin role.
     *
     * @param ExportModel $ex
     */
    protected function promoteAdmin(ExportModel $ex)
    {
        // Find the Vanlla superadmin (User.Admin = 1) and make them an Admin.
        $result = $ex->dbImport()
            ->table('PORT_User')
            ->where('Admin', '>', 0)
            ->first();

        if (isset($result->UserID, $result->Name, $result->Email)) {
            // Add the admin.
            $ex->dbImport()
                ->table($ex->tarPrefix . 'group_user')
                ->insert(['group_id' => 1, 'user_id' => $result->UserID]);

            // Report promotion.
            $ex->comment('Promoted to Admin: ' . $result->Name . ' (' . $result->Email . ')');
        } else {
            // Report failure.
            $ex->comment('No user found to promote to Admin. (Searching for Admin=1 flag on PORT_User.)');
        }
    }
}
