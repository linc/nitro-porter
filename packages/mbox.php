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
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @author Lincoln Russell lincolnwebs.com
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['mbox'] = array('name' => '.mbox files', 'prefix' => '');
$supported['mbox']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
);

class Mbox extends ExportController {

    /** @var array Required tables => columns */
    protected $sourceTables = array(
        'mbox' => array('Subject', 'Sender', 'Date', 'Body', 'Folder')
    );

    /**
     * Forum-specific export format.
     * @param ExportModel $ex
     */
    protected function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('mbox');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Begin
        $ex->beginExport('', 'Mbox', array());


        // Temporary user table
        $ex->query('create table :_mbox_user (UserID int AUTO_INCREMENT, Name varchar(255), Email varchar(255), PRIMARY KEY (UserID))');
        $result = $ex->query('select Sender from :_mbox group by Sender', true);

        // Users, pt 1: Build ref array; Parse name & email out - strip quotes, <, >
        $users = array();
        while ($row = mysql_fetch_assoc($result)) {
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
            $ex->query('insert into :_mbox_user (Name, Email)
            values ("' . mysql_real_escape_string($name) . '", "' . mysql_real_escape_string($email) . '")');
            $userID = mysql_insert_id();
            // Overwrite user list with new UserID instead of name
            $users[$email] = $userID;
        }


        // Temporary category table
        $ex->query('create table :_mbox_category (CategoryID int AUTO_INCREMENT, Name varchar(255),
         PRIMARY KEY (CategoryID))');
        $result = $ex->query('select Folder from :_mbox group by Folder', true);
        // Parse name out & build ref array
        $categories = array();
        while ($row = mysql_fetch_assoc($result)) {
            $ex->query('insert into :_mbox_category (Name)
            values ("' . mysql_real_escape_string($row["Folder"]) . '")');
            $categoryID = mysql_insert_id();
            $categories[$row["Folder"]] = $categoryID;
        }


        // Temporary post table
        $ex->query('create table :_mbox_post (PostID int AUTO_INCREMENT, DiscussionID int,
         IsDiscussion tinyint default 0, InsertUserID int, Name varchar(255), Body text, DateInserted datetime,
         CategoryID int, PRIMARY KEY (PostID))');
        $result = $ex->query('select * from :_mbox', true);
        // Parse name, body, date, userid, categoryid
        while ($row = mysql_fetch_assoc($result)) {
            // Assemble posts into a format we can actually export.
            // Subject: trim quotes, 're: ', 'fwd: ', 'fw: ', [category]
            $name = trim(preg_replace('#^(re:)|(fwd?:) #i', '', trim($row['Subject'], '"')));
            $name = trim(preg_replace('#^\[[0-9a-zA-Z_-]*] #', '', $name));
            $email = $this->parseEmail($row['Sender']);
            $userID = (isset($users[$email])) ? $users[$email] : 0;
            $ex->query('insert into :_mbox_post (Name, InsertUserID, CategoryID, DateInserted, Body)
            values ("' . mysql_real_escape_string($name) . '",
               ' . $userID . ',
               ' . $categories[$row['Folder']] . ',
               from_unixtime(' . strtotime($row['Date']) . '),
               "' . mysql_real_escape_string($this->parseBody($row['Body'])) . '")');
        }

        // Decide which posts are OPs
        $result = $ex->query('select PostID from (select * from :_mbox_post order by DateInserted asc) x group by Name',
            true);
        $discussions = array();
        while ($row = mysql_fetch_assoc($result)) {
            $discussions[] = $row['PostID'];
        }
        $ex->query('update :_mbox_post set IsDiscussion = 1 where PostID in (' . implode(",", $discussions) . ')');

        // Thread the comments
        $result = $ex->query('select c.PostID, d.PostID as DiscussionID from :_mbox_post c
         left join :_mbox_post d on c.Name like d.Name and d.IsDiscussion = 1
         where c.IsDiscussion = 0', true);
        while ($row = mysql_fetch_assoc($result)) {
            $ex->query('update :_mbox_post set DiscussionID = ' . $row['DiscussionID'] . '  where PostID = ' . $row['PostID']);
        }


        // Users
        $user_Map = array();
        $ex->exportTable('User', "
         select u.*,
            NOW() as DateInserted,
            'Reset' as HashMethod
         from :_mbox_user u", $user_Map);


        // Categories
        $category_Map = array();
        $ex->exportTable('Category', "
      select *
      from :_mbox_category", $category_Map);


        // Discussions
        $discussion_Map = array(
            'PostID' => 'DiscussionID'
        );
        $ex->exportTable('Discussion', "
      select p.PostID, p.DateInserted, p.Name, p.Body, p.InsertUserID, p.CategoryID,
         'Html' as Format
       from :_mbox_post p where IsDiscussion = 1", $discussion_Map);


        // Comments
        $comment_Map = array(
            'PostID' => 'CommentID'
        );
        $ex->exportTable('Comment',
            "select p.*,
         'Html' as Format
       from :_mbox_post p
       where IsDiscussion = 0", $comment_Map);


        // Remove Temporary tables
        //$ex->Query('drop table :_mbox_post');
        //$ex->Query('drop table :_mbox_category');
        //$ex->Query('drop table :_mbox_user');

        // End
        $ex->endExport();
//      echo implode("\n\n", $ex->Queries);
    }

    /**
     * Grab the email from the User field.
     */
    public function parseEmail($email) {
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
    public function parseBody($body) {
        $body = preg_replace('#Subject:\s*(.*)\s*From:\s*(.*)\s*Date:\s*(.*)\s*To:\s*(.*)\s*(CC:\s*(.*)\s*)?#', '',
            $body);
        $body = preg_replace('#\s*From: ([a-zA-Z0-9_-]*)@(.*)#', '', $body);
        $body = explode("____________", $body);
        $body = explode("----- Original Message -----", $body[0]);

        return trim($body[0]);
    }
}

// Closing PHP tag required. (make.php)
?>
