<?php

/**
 * ASP Playground exporter tool
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class AspPlayground extends Source
{
    public const SUPPORTED = [
        'name' => 'ASP Playground',
        'prefix' => 'pgd_',
        'charset_table' => 'Threads',
        'options' => [
        ],
        'features' => [
            'Users' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Bookmarks' => 1,
            'Polls' => 0, // Challenges noted inline below.
            'PrivateMessages' => 0, // Don't appear to be threaded in a rational way (see table PMsg).
        ]
    ];

    protected const FLAGS = [
        'hasDiscussionBody' => false,
    ];

    /**
     * @param ExportModel $ex
     */
    public function run($ex)
    {
        $this->users($ex);
        $this->signatures($ex);

        $this->categories($ex);

        $this->discussions($ex);
        $this->comments($ex);
        $this->bookmarks($ex);
        $this->polls($ex);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $map = [
            'Mem' => 'UserID',
            'Login' => 'Name',
            'Email' => 'Email',
            'Userpass' => 'Password',
            'totalPosts' => 'CountComments',
            'banned' => 'Banned',
            'dateSignUp' => 'DateInserted',
            'lastLogin' => 'DateLastActive',
            'location' => 'Location',
        ];
        $ex->export(
            'User',
            "select m.*, 'Text' as HashMethod
                from :_Members m",
            $map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function signatures(ExportModel $ex): void
    {
        $ex->export(
            'UserMeta',
            "select Mem, 'Plugin.Signatures.Sig' as `Name`, signature as `Value`
            from :_Members
            where signature <> ''

            union all

            select Mem, 'Plugin.Signatures.Format' as `Name`, 'BBCode' as `Value`
            from :_Members
            where signature <> ''"
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $map = [
            'ForumID' => 'CategoryID',
            'ForumTitle' => 'Name',
            'ForumDesc' => 'Description',
            'Sort' => 'Sort',
            'lastModTime' => 'DateUpdated',
            'Total' => 'CountComments',
            'Topics' => 'CountDiscussions',
            'parent' => 'ParentCategoryID',
        ];
        $ex->export(
            'Category',
            "select f.*
                from :_Forums f
                where linkTarget != 1", // External link categories have linkTarget==1
            $map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $map = [
            'messageID' => 'DiscussionID',
            'ForumID' => 'CategoryID',
            'mem' => 'InsertUserID',
            'dateCreated' => 'DateInserted',
            'Subject' => 'Name',
            'hits' => 'CountViews',
            'lastupdate' => 'DateLastComment',
        ];
        $ex->export(
            'Discussion',
            "select t.*, m.Body
                from :_Threads t
                left join :_Messages m on m.messageID = t.messageID",
            $map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $map = [
            'messageID' => 'CommentID',
            'threadID' => 'DiscussionID',
            'parent' => 'ForeignID', // Preserve tree just in case.
            'Mem' => 'InsertUserID',
            'dateCreated' => 'DateInserted',
            'Body' => 'Body',
        ];

        // Avoid adding OP redundantly.
        $skipOP = '';
        if ($this->getDiscussionBodyMode()) {
            $skipOP = "where messageID != threadID";
        }
        $ex->export(
            'Comment',
            "select m.*, 'BBCode' as Format
                from :_Messages m
                $skipOP",
            $map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function bookmarks(ExportModel $ex): void
    {
        $map = [
            'Mem' => 'UserID',
            'threadID' => 'DiscussionID',
        ];
        $ex->export(
            'UserDiscussion',
            "select *, '1' as Bookmarked
                from :_Subscription
                where threadID is not null and isActive = 1",
            $map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function polls(ExportModel $ex): void
    {
        // Tables needed: Poll, PollDefinition, PollLog
        // Not attached to discussions, only forums.
        // Some allow multiple selections. Optional guest voting.
    }
}
