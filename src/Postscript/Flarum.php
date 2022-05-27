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
        $this->buildUserMentions($ex);
    }

    /**
     * Find mentions in posts and record to database table.
     */
    protected function buildUserMentions(ExportModel $ex)
    {
        // Prepare mentions table.
        $this->storage->prepare('post_mentions_user', self::DB_STRUCTURE_POST_MENTIONS_USER);

        // Get post data.
        $posts = $this->connection->dbm()
            ->table($ex->tarPrefix . 'posts')
            ->select(['id', 'discussion_id', 'content']);

        // Find & record mentions in batches.
        foreach ($posts->cursor() as $post) {
            // Find converted mentions and connect to userID.
            $mentions = [];
            preg_match_all(
                '/<USERMENTION .* id="(?<userids>[0-9]*)".*\/USERMENTION>/U',
                $post->content,
                $mentions
            );
            foreach ($mentions['userids'] as $userid) {
                // There can be multiple userids per post.
                $this->storage->stream([
                    'post_id' => $post->id,
                    'mentions_user_id' => (int)$userid
                ], self::DB_STRUCTURE_POST_MENTIONS_USER);
            }
        }

        // Insert remaining mentions.
        $this->storage->endStream();
    }
}
