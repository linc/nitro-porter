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
 *    Name (subject), User (from), Body (body), Date (date), Category (manually set to name of each mbox)
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
      'mbox' => array('Name', 'User', 'Date', 'Body', 'Category')
   );
   
   /**
    * Forum-specific export format.
    * @param ExportModel $Ex
    */
   protected function ForumExport($Ex) {
      // Begin
      $Ex->BeginExport('', 'Mbox', array());


      // Temporary user table
      $Ex->Query('create table :_mbox_user (id int, name varchar(255), email varchar(255))');
      $Ex->Query('alter table :_mbox_user ADD PRIMARY KEY(id)');
      $Result = $Ex->Query('select User from :_mbox group by User');
      // Parse name & email out - strip quotes, <, >
      // Build ref array

      // Temporary category table
      $Ex->Query('create table :_mbox_category (id int, name varchar(255))');
      $Ex->Query('alter table :_mbox_category ADD PRIMARY KEY(id)');
      $Result = $Ex->Query('select Category from :_mbox group by Category');
      // Parse name out & build ref array

      // Temporary post table
      $Ex->Query('create table :_mbox_post (id int, userid int, name varchar(255), body text, date datetime)');
      $Ex->Query('alter table :_mbox_post ADD PRIMARY KEY(id)');
      $Result = $Ex->Query('select * from :_mbox');
      // Parse name, body, date, userid, categoryid
      // Subject: trim quotes, 're: ', 'fwd: ', 'fw: ', [category]
      // Body: strip headers, signatures, and quoted content


      // Users
      $User_Map = array(
         //'ID_MEMBER'=>'UserID',

      );
      $Ex->ExportTable('User', "
         select u.*,
            NOW() as DateInserted,
         from :_mbox_user u", $User_Map);


      // Categories
      $Category_Map = array(
          //'Name' => array('Column' => 'Name', 'Filter' => array($this, 'DecodeNumericEntity'))
      );

      $Ex->ExportTable('Category', "
      select *
      from :_mbox_category", $Category_Map);


      // Discussions
      $Discussion_Map = array(
         //'ID_TOPIC' => 'DiscussionID',
         //'subject' => array('Column'=>'Name', 'Filter' => array($this, 'DecodeNumericEntity')), //,'Filter'=>'bb2html'),
      );
      $Ex->ExportTable('Discussion', "
      select p.*,
         'Html' as Format
       from :_mbox_post p", $Discussion_Map);


      // Comments
      $Comment_Map = array(
         //'ID_MSG' => 'CommentID',
      );
      $Ex->ExportTable('Comment', 
      "select p.*,
         'Html' as Format
       from :_mbox_post p;
       ", $Comment_Map);


      // Remove Temporary tables
      $Ex->Query('drop table :_mbox_post');
      $Ex->Query('drop table :_mbox_category');
      $Ex->Query('drop table :_mbox_user');

      // End
      $Ex->EndExport();
//      echo implode("\n\n", $Ex->Queries);
   }
}
?>