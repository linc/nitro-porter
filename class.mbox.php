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
 * Export all messages in each mbox to CSV file. Watch status bar at bottom of Thunderbird for progress.
 * Import settings: Escape is “ (double quote) ONLY
 * Import all CSVs to 1 table named ‘mbox’ with text fields:
 *    Subject, Sender, Body, Date, Folder (manually set to name of each mbox)
 *
 * @copyright Vanilla Forums 2013
 * @author Lincoln Russell lincolnwebs.com
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
 
$Supported['Mbox'] = array('name'=>'Mbox (Mailbox)', 'prefix' => '');

class Mbox extends ExportController {

   /** @var array Required tables => columns */
   protected $SourceTables = array(
      'mbox' => array('Subject', 'Sender', 'Date', 'Body', 'Folder')
   );
   
   /**
    * Forum-specific export format.
    * @param ExportModel $Ex
    */
   protected function ForumExport($Ex) {
      // Begin
      $Ex->BeginExport('', 'Mbox', array());


      // Temporary user table
      $Ex->Query('create table :_mbox_user (UserID int AUTO_INCREMENT, Name varchar(255), Email varchar(255), PRIMARY KEY (UserID))');
      $Result = $Ex->Query('select Sender from :_mbox group by Sender', TRUE);
      // Parse name & email out - strip quotes, <, >
      // Build ref array
      $Users = array();
      while ($Row = mysql_fetch_assoc($Result)) {
         // Most senders are "Name <Email>"
         $NameParts = explode('<', trim($Row['Sender'],'"'));
         // Sometimes the sender is just <email>
         if ($NameParts[0] == '')
            $Name = trim(str_replace('>', '', $NameParts[1]));
         else // Normal?
            $Name = trim(str_replace('\\', '', $NameParts[0]));
         if (strstr($Name, '@') !== FALSE) {
            // Only wound up with an email
            $Name = explode('@', $Name);
            $Name  = $Name[0];
         }
         $Email = $this->ParseEmail($Row['Sender']);
         $Ex->Query('insert into :_mbox_user (Name, Email)
            values ("'.mysql_real_escape_string($Name).'", "'.mysql_real_escape_string($Email).'")');
         $UserID = mysql_insert_id();
         $Users[$Email] = $UserID;
      }

      // Temporary category table
      $Ex->Query('create table :_mbox_category (CategoryID int AUTO_INCREMENT, Name varchar(255),
         PRIMARY KEY (CategoryID))');
      $Result = $Ex->Query('select Folder from :_mbox group by Folder', TRUE);
      // Parse name out & build ref array
      $Categories = array();
      while ($Row = mysql_fetch_assoc($Result)) {
         $Ex->Query('insert into :_mbox_category (Name)
            values ("'.mysql_real_escape_string($Row["Folder"]).'")');
         $CategoryID = mysql_insert_id();
         $Categories[$Row["Folder"]] = $CategoryID;
      }

      // Temporary post table
      $Ex->Query('create table :_mbox_post (PostID int AUTO_INCREMENT, DiscussionID int,
         IsDiscussion tinyint default 0, InsertUserID int, Name varchar(255), Body text, DateInserted datetime,
         CategoryID int, PRIMARY KEY (PostID))');
      $Result = $Ex->Query('select * from :_mbox', TRUE);
      // Parse name, body, date, userid, categoryid
      while ($Row = mysql_fetch_assoc($Result)) {
         // Assemble posts into a format we can actually export.
         // Subject: trim quotes, 're: ', 'fwd: ', 'fw: ', [category]
         $Name = trim(preg_replace('#^(re:)|(fwd?:) #i', '', trim($Row['Subject'],'"')));
         $Name = trim(preg_replace('#^\[[0-9a-zA-Z_-]*] #', '', $Name));
         $Email = $this->ParseEmail($Row['Sender']);
         $UserID = (isset($Users[$Email])) ? $Users[$Email] : 0;
         $Ex->Query('insert into :_mbox_post (Name, InsertUserID, CategoryID, DateInserted, Body)
            values ("'.mysql_real_escape_string($Name).'",
               '.$UserID.',
               '.$Categories[$Row['Folder']].',
               from_unixtime('.strtotime($Row['Date']).'),
               "'.mysql_real_escape_string($this->ParseBody($Row['Body'])).'")');
      }

      // Decide which posts are OPs
      $Result = $Ex->Query('select PostID from (select * from :_mbox_post order by DateInserted asc) x group by Name', TRUE);
      $Discussions = array();
      while ($Row = mysql_fetch_assoc($Result)) {
         $Discussions[] = $Row['PostID'];
      }
      $Ex->Query('update :_mbox_post set IsDiscussion = 1 where PostID in ('.implode(",", $Discussions).')');

      // Thread the comments
      $Result = $Ex->Query('select c.PostID, d.PostID as DiscussionID from :_mbox_post c
         left join :_mbox_post d on c.Name like d.Name and d.IsDiscussion = 1
         where c.IsDiscussion = 0', TRUE);
      while ($Row = mysql_fetch_assoc($Result)) {
         $Ex->Query('update :_mbox_post set DiscussionID = '.$Row['DiscussionID'].'  where PostID = '.$Row['PostID']);
      }


      // Users
      $User_Map = array();
      $Ex->ExportTable('User', "
         select u.*,
            NOW() as DateInserted,
            'Reset' as HashMethod
         from :_mbox_user u", $User_Map);


      // Categories
      $Category_Map = array();
      $Ex->ExportTable('Category', "
      select *
      from :_mbox_category", $Category_Map);


      // Discussions
      $Discussion_Map = array(
         'PostID' => 'DiscussionID'
      );
      $Ex->ExportTable('Discussion', "
      select p.PostID, p.DateInserted, p.Name, p.Body, p.InsertUserID, p.CategoryID,
         'Html' as Format
       from :_mbox_post p where IsDiscussion = 1", $Discussion_Map);


      // Comments
      $Comment_Map = array(
         'PostID' => 'CommentID'
      );
      $Ex->ExportTable('Comment', 
      "select p.*,
         'Html' as Format
       from :_mbox_post p
       where IsDiscussion = 0", $Comment_Map);


      // Remove Temporary tables
      //$Ex->Query('drop table :_mbox_post');
      //$Ex->Query('drop table :_mbox_category');
      //$Ex->Query('drop table :_mbox_user');

      // End
      $Ex->EndExport();
//      echo implode("\n\n", $Ex->Queries);
   }

   /**
    * Grab the email from the User field.
    */
   public function ParseEmail($Email) {
      $EmailBits = explode('<',$Email);
      if (!isset($EmailBits[1]))
         return $Email;

      $EmailBits = explode('>',$EmailBits[1]);
      return trim($EmailBits[0]);
   }

   /**
    * Body: strip headers, signatures, fwds.
    */
   public function ParseBody($Body) {
      $Body = preg_replace('#Subject:\s*(.*)\s*From:\s*(.*)\s*Date:\s*(.*)\s*To:\s*(.*)\s*(CC:\s*(.*)\s*)?#', '', $Body);
      $Body = preg_replace('#\s*From: ([a-zA-Z0-9_-]*)@(.*)#', '', $Body);
      $Body = explode("____________", $Body);
      $Body = explode("----- Original Message -----", $Body[0]);
      return trim($Body[0]);
   }
}
?>