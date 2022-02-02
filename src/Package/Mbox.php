<?php

/**
 * MBOX exporter tool.
 *
 * Got a small listserv? Get 'er in mbox format and follow these instructions to turn it into a forum.
 *    This will currently load your entire history into memory so it's not for doing huge lists at once.
 *    You will need high resource limits for your server config.
 * Install Thunderbird & extension ImportExportTools
 *    https://www.google.com/url?q=https%3A%2F%2Faddons.mozilla.org%2Fen-us%2Fthunderbird%2Faddon%2Fimportexporttools%2F&sa=D&sntz=1&usg=AFQjCNEw-oR9Y4Y_DEvD1qF_7TNcS1_v1w
 * Set the max size on all fields to 255 (in the addon’s preferences)
 * Reading an mbox file with Thunderbird:
 *    https://www.google.com/url?q=https%3A%2F%2Fcommons.lbl.gov%2Fdisplay%2F~jwelcher%40lbl.gov%2FReading%2Ban%2Bmbox%2Bfile%2Bwith%2BThunderbird&sa=D&sntz=1&usg=AFQjCNGs5UFFhrHvGPbfwOZUdeVjmu_XAQ
 * Right click each mbox -> ImportExportTools -> "Export all messages in this folder" -> "Spreadsheet (CSV)".
 *    Watch status bar at bottom of Thunderbird for progress.
 * Import settings: Escape is “ (double quote) ONLY
 * Import all CSVs to 1 table named ‘mbox’ with text fields:
 *    Subject, Sender, Body, Date, Folder (manually set to name of each mbox)
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Package;

use Porter\Package;
use Porter\ExportModel;

class Mbox extends Package
{
    public const SUPPORTED = [
        'name' => '.mbox files',
        'prefix' => '',
        'features' => [
            'Users' => 1,
            'Passwords' => 0,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 0,
            'Avatars' => 0,
            'PrivateMessages' => 0,
            'Signatures' => 0,
            'Attachments' => 0,
            'Bookmarks' => 0,
            'Permissions' => 0,
            'Badges' => 0,
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 0,
            'Reactions' => 0,
            'Articles' => 0,
        ]
    ];

    /**
     * @var array Required tables => columns
     */
    public $sourceTables = array(
        'mbox' => array('Subject', 'Sender', 'Date', 'Body', 'Folder')
    );

    /**
     * Forum-specific export format.
     *
     * @param ExportModel $ex
     */
    public function run($ex)
    {
        $this->setup($ex); // Here be dragons.

        $this->users($ex);

        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);

        // Remove Temporary tables
        //$ex->Query('drop table :_mbox_post');
        //$ex->Query('drop table :_mbox_category');
        //$ex->Query('drop table :_mbox_user');
    }

    /**
     * Grab the email from the User field.
     */
    public function parseEmail($email)
    {
        $emailBits = explode('<', $email);
        if (!isset($emailBits[1])) {
            return $email;
        }

        $emailBits = explode('>', $emailBits[1]);

        return trim($emailBits[0]);
    }

    /**
     * Body: strip headers, signatures, fwds.
     */
    public function parseBody($body)
    {
        $body = preg_replace(
            '#Subject:\s*(.*)\s*From:\s*(.*)\s*Date:\s*(.*)\s*To:\s*(.*)\s*(CC:\s*(.*)\s*)?#',
            '',
            $body
        );
        $body = preg_replace('#\s*From: ([a-zA-Z0-9_-]*)@(.*)#', '', $body);
        $body = explode("____________", $body);
        $body = explode("----- Original Message -----", $body[0]);

        return trim($body[0]);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $user_Map = array();
        $ex->exportTable(
            'User',
            "
                select u.*,
                    NOW() as DateInserted,
                    'Reset' as HashMethod
                from :_mbox_user u",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array();
        $ex->exportTable(
            'Category',
            "
                select *
                from :_mbox_category",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
            'PostID' => 'DiscussionID'
        );
        $ex->exportTable(
            'Discussion',
            "
            select p.PostID, p.DateInserted, p.Name, p.Body, p.InsertUserID, p.CategoryID, 'Html' as Format
            from :_mbox_post p where IsDiscussion = 1",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $comment_Map = array(
            'PostID' => 'CommentID'
        );
        $ex->exportTable(
            'Comment',
            "select p.*, 'Html' as Format
                from :_mbox_post p
                where IsDiscussion = 0",
            $comment_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function setup(ExportModel $ex): void
    {
// Temporary user table
        $ex->query('create table :_mbox_user
            (UserID int AUTO_INCREMENT, Name varchar(255), Email varchar(255), PRIMARY KEY (UserID))');
        $result = $ex->query('select Sender from :_mbox group by Sender', true);

        // Users, pt 1: Build ref array; Parse name & email out - strip quotes, <, >
        $users = array();
        while ($row = $result->nextResultRow()) {
            // Most senders are "Name <Email>"
            $nameParts = explode('<', trim($row['Sender'], '"'));
            // Sometimes the sender is just <email>
            if ($nameParts[0] == '') {
                $name = trim(str_replace('>', '', $nameParts[1]));
            } else // Normal?
            {
                $name = trim(str_replace('\\', '', $nameParts[0]));
            }
            if (strstr($name, '@') !== false) {
                // Only wound up with an email
                $name = explode('@', $name);
                $name = $name[0];
            }
            $email = $this->parseEmail($row['Sender']);

            // Compile by unique email
            $users[$email] = $name;
        }

        // Users, pt 2: loop thru unique emails
        foreach ($users as $email => $name) {
            $ex->query(
                'insert into :_mbox_user (Name, Email)
                values ("' . $ex->escape($name) . '", "' . $ex->escape($email) . '")'
            );
            $userID = 0;
            $maxRes = $ex->query("select max(UserID) as id from :_mbox_user");
            while ($max = $maxRes->nextResultRow()) {
                $userID = $max['id'];
            }
            // Overwrite user list with new UserID instead of name
            $users[$email] = $userID;
        }


        // Temporary category table
        $ex->query(
            'create table :_mbox_category (CategoryID int AUTO_INCREMENT, Name varchar(255),
            PRIMARY KEY (CategoryID))'
        );
        $result = $ex->query('select Folder from :_mbox group by Folder', true);
        // Parse name out & build ref array
        $categories = array();
        while ($row = $result->nextResultRow()) {
            $ex->query(
                'insert into :_mbox_category (Name)
                values ("' . $ex->escape($row["Folder"]) . '")'
            );
            $categoryID = 0;
            $maxRes = $ex->query("select max(CategoryID) as id from :_mbox_category");
            while ($max = $maxRes->nextResultRow()) {
                $categoryID = $max['id'];
            }
            $categories[$row["Folder"]] = $categoryID;
        }


        // Temporary post table
        $ex->query(
            'create table :_mbox_post (PostID int AUTO_INCREMENT, DiscussionID int,
            IsDiscussion tinyint default 0, InsertUserID int, Name varchar(255), Body text, DateInserted datetime,
            CategoryID int, PRIMARY KEY (PostID))'
        );
        $result = $ex->query('select * from :_mbox', true);
        // Parse name, body, date, userid, categoryid
        while ($row = $result->nextResultRow()) {
            // Assemble posts into a format we can actually export.
            // Subject: trim quotes, 're: ', 'fwd: ', 'fw: ', [category]
            $name = trim(preg_replace('#^(re:)|(fwd?:) #i', '', trim($row['Subject'], '"')));
            $name = trim(preg_replace('#^\[[0-9a-zA-Z_-]*] #', '', $name));
            $email = $this->parseEmail($row['Sender']);
            $userID = (isset($users[$email])) ? $users[$email] : 0;
            $ex->query(
                'insert into :_mbox_post (Name, InsertUserID, CategoryID, DateInserted, Body)
                values ("' . $ex->escape($name) . '",
               ' . $userID . ',
               ' . $categories[$row['Folder']] . ',
               from_unixtime(' . strtotime($row['Date']) . '),
               "' . $ex->escape($this->parseBody($row['Body'])) . '")'
            );
        }

        // Decide which posts are OPs
        $result = $ex->query(
            'select PostID from (select * from :_mbox_post order by DateInserted asc) x group by Name'
        );
        $discussions = array();
        while ($row = $result->nextResultRow()) {
            $discussions[] = $row['PostID'];
        }
        $ex->query('update :_mbox_post set IsDiscussion = 1 where PostID in (' . implode(",", $discussions) . ')');

        // Thread the comments
        $result = $ex->query(
            'select c.PostID, d.PostID as DiscussionID from :_mbox_post c
            left join :_mbox_post d on c.Name like d.Name and d.IsDiscussion = 1
            where c.IsDiscussion = 0'
        );
        while ($row = $result->nextResultRow()) {
            $ex->query('update :_mbox_post set DiscussionID = ' . $row['DiscussionID'] . '
                where PostID = ' . $row['PostID']);
        }
    }
}
