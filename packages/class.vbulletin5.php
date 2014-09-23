<?php
/**
 * vBulletin 5 Connect exporter tool
 *
 * @copyright Vanilla Forums Inc. 2014
 * @license Proprietary
 * @package VanillaPorter
 */

$Supported['vbulletin5'] = array('name'=> 'vBulletin 5 Connect', 'prefix'=>'vb_');
$Supported['vbulletin5']['CommandLine'] = array(
   'attachments' => array('Whether or not to export attachments.', 'Sx' => '::'),
   'avatars' => array('Whether or not to export avatars.', 'Sx' => '::', 'Field' => 'avatars', 'Short' => 'a', 'Default' => ''),
   //'noexport' => array('Whether or not to skip the export.', 'Sx' => '::'),
   //'mindate' => array('A date to import from.'),
   //'forumid' => array('Only export 1 forum')
);

class Vbulletin5 extends Vbulletin {
   /** @var array Required tables => columns. */
   protected $SourceTables = array(
      'contenttype' => array('contenttypeid','class'),
      'node' => array('nodeid','description','title','description','userid','publishdate'),
      'text' => array('nodeid','rawtext'),
      'user' => array('userid','username','password','email','referrerid','timezoneoffset','posts','salt',
         'birthday_search','joindate','lastvisit','lastactivity','membergroupids','usergroupid','usertitle', 'avatarid'),
      'userfield' => array('userid'),
      'usergroup'=> array('usergroupid','title','description'),
      'usertitle' => array(),
   );

   /**
    *
    * @param ExportModel $Ex 
    */
   public function ForumExport($Ex) {
      // Allow limited export of 1 category via ?forumid=ID
      $ForumID = $this->Param('forumid');
      if ($ForumID)
         $ForumWhere = ' and n.nodeid '.(strpos($ForumID, ', ') === FALSE ? "= $ForumID" : "in ($ForumID)");
      else
         $ForumWhere = '';

      // Determine the character set
      $CharacterSet = $Ex->GetCharacterSet('nodes');
      if ($CharacterSet)
         $Ex->CharacterSet = $CharacterSet;
      
      $Ex->BeginExport('', 'vBulletin 5 Connect');

      $this->ExportBlobs(
         $this->Param('attachments'),
         $this->Param('avatars'),
         $ForumWhere
      );

      if ($this->Param('noexport')) {
         $Ex->Comment('Skipping the export.');
         $Ex->EndExport();
         return;
      }

      // Check to see if there is a max date.
      $MinDate = $this->Param('mindate');
      if ($MinDate) {
         $MinDate = strtotime($MinDate);
         $Ex->Comment("Min topic date ($MinDate): ".date('c', $MinDate));
      }
      $Now = time();

      $cdn = $this->Param('cdn', '');


      // Grab all of the ranks.
      $Ranks = $Ex->Get("select * from :_usertitle order by minposts desc", 'usertitleid');


      // Users
      $User_Map = array(
         'userid'=>'UserID',
         'username'=>'Name',
         'password2'=>'Password',
         'email'=>'Email',
         'referrerid'=>'InviteUserID',
         'timezoneoffset'=>'HourOffset',
         'ipaddress' => 'LastIPAddress',
         'ipaddress2' => 'InsertIPAddress',
         'usertitle' => 'Title',
         'posts' => array('Column' => 'RankID', 'Filter' => function($Value) use ($Ranks) {
               // Look  up the posts in the ranks table.
               foreach ($Ranks as $RankID => $Row) {
                  if ($Value >= $Row['minposts'])
                     return $RankID;
               }
               return NULL;
            })
      );

      // Use file avatar or the result of our blob export?
      if ($this->GetConfig('usefileavatar'))
         $User_Map['filephoto'] = 'Photo';
      else
         $User_Map['customphoto'] = 'Photo';

      $Ex->ExportTable('User', "select u.*,
            ipaddress as ipaddress2,
				concat(`password`, salt) as password2,
            DATE_FORMAT(birthday_search,GET_FORMAT(DATE,'ISO')) as DateOfBirth,
            FROM_UNIXTIME(joindate) as DateFirstVisit,
            FROM_UNIXTIME(lastvisit) as DateLastActive,
            FROM_UNIXTIME(joindate) as DateInserted,
            FROM_UNIXTIME(lastactivity) as DateUpdated,
            case when avatarrevision > 0 then concat('$cdn', 'userpics/avatar', u.userid, '_', avatarrevision, '.gif')
                 when av.avatarpath is not null then av.avatarpath
                 else null
                 end as filephoto,
            {$this->AvatarSelect},
            case when ub.userid is not null then 1 else 0 end as Banned,
            'vbulletin' as HashMethod
         from :_user u
         left join :_customavatar a
         	on u.userid = a.userid
         left join :_avatar av
         	on u.avatarid = av.avatarid
         left join :_userban ub
       	 	on u.userid = ub.userid and ub.liftdate <= now() ", $User_Map);  // ":_" will be replace by database prefix
      //ipdata - contains all IP records for user actions: view,visit,register,logon,logoff


      // Roles
      $Role_Map = array(
         'usergroupid'=>'RoleID',
         'title'=>'Name',
         'description'=>'Description'
      );
      $Ex->ExportTable('Role', 'select * from :_usergroup', $Role_Map);


      // UserRoles
      $UserRole_Map = array(
         'userid'=>'UserID',
         'usergroupid'=>'RoleID'
      );
      $Ex->Query("CREATE TEMPORARY TABLE VbulletinRoles (userid INT UNSIGNED NOT NULL, usergroupid INT UNSIGNED NOT NULL)");
      # Put primary groups into tmp table
      $Ex->Query("insert into VbulletinRoles (userid, usergroupid) select userid, usergroupid from :_user");
      # Put stupid CSV column into tmp table
      $SecondaryRoles = $Ex->Query("select userid, usergroupid, membergroupids from :_user", TRUE);
      if (is_resource($SecondaryRoles)) {
         while (($Row = @mysql_fetch_assoc($SecondaryRoles)) !== false) {
            if($Row['membergroupids']!='') {
               $Groups = explode(',',$Row['membergroupids']);
               foreach($Groups as $GroupID) {
                  $Ex->Query("insert into VbulletinRoles (userid, usergroupid) values({$Row['userid']},{$GroupID})", TRUE);
               }
            }
         }
      }
      # Export from our tmp table and drop
      $Ex->ExportTable('UserRole', 'select distinct userid, usergroupid from VbulletinRoles', $UserRole_Map);
      $Ex->Query("DROP TABLE IF EXISTS VbulletinRoles");


      // Permissions.
      $Permissions_Map = array(
         'usergroupid' => 'RoleID',
         'title' => array('Column' => 'Garden.SignIn.Allow', 'Filter' => array($this, 'SignInPermission')),
         'genericpermissions' => array('Column' => 'GenericPermissions', 'type' => 'int'),
         'forumpermissions' => array('Column' => 'ForumPermissions', 'type' => 'int')
      );
      $this->AddPermissionColumns(self::$Permissions, $Permissions_Map);
      $Ex->ExportTable('Permission', 'select * from :_usergroup', $Permissions_Map);


      // UserMeta
      if ($this->Param('attachments')) {
         $Ex->Query("CREATE TEMPORARY TABLE VbulletinUserMeta (`UserID` INT NOT NULL ,`Name` VARCHAR( 255 ) NOT NULL ,`Value` text NOT NULL)");
         # Standard vB user data
         $UserFields = array('usertitle' => 'Title', 'homepage' => 'Website', 'skype' => 'Skype', 'styleid' => 'StyleID');
         foreach($UserFields as $Field => $InsertAs)
            $Ex->Query("insert into VbulletinUserMeta (UserID, Name, Value) select userid, 'Profile.$InsertAs', $Field from :_user where $Field != ''");
         # Dynamic vB user data (userfield)
         $ProfileFields = $Ex->Query("select varname, text from :_phrase where product='vbulletin' and fieldname='cprofilefield' and varname like 'field%_title'");
         if (is_resource($ProfileFields)) {
            $ProfileQueries = array();
            while ($Field = @mysql_fetch_assoc($ProfileFields)) {
               $Column = str_replace('_title', '', $Field['varname']);
               $Name = preg_replace('/[^a-zA-Z0-9_-\s]/', '', $Field['text']);
               $ProfileQueries[] = "insert into VbulletinUserMeta (UserID, Name, Value)
                  select userid, 'Profile.".$Name."', ".$Column." from :_userfield where ".$Column." != ''";
            }
            foreach ($ProfileQueries as $Query) {
               $Ex->Query($Query);
            }
         }
      }


      // Ranks
      $Rank_Map = array(
         'usertitleid' => 'RankID',
         'title' => 'Name',
         'title2' => 'Label',
         'minposts' => array('Column' => 'Attributes', 'Filter' => function($Value) {
               $Result = array(
                  'Criteria' => array(
                     'CountPosts' => $Value
                  )
               );

               return serialize($Result);
            }),
         'level' => array('Column' => 'Level', 'Filter' => function($Value) {
               static $Level = 1;
               return $Level++;
            })
      );
      $Ex->ExportTable('Rank', "
         select ut.*, ut.title as title2, 0 as level
         from :_usertitle ut
         order by ut.minposts", $Rank_Map);


      /// Signatures

      /// Notes

      /// Warnings

      /// Activity (Wall)


      // Category.
      $Channels = array();
      $CategoryIDs = array();
      $HomeID = 0;
      $PrivateMessagesID = 0;

      // Filter Channels down to Forum tree
      $ChannelResult = $Ex->Query("select n.* from :_node n
         left join :_contenttype c on n.contenttypeid = c.contenttypeid
         where c.class = 'Channel'");

      while ($Channel = mysql_fetch_array($ChannelResult)) {
         $Channels[$Channel['nodeid']] = $Channel;
         if ($Channel['title'] == 'Forum')
            $HomeID = $Channel['nodeid'];
         if ($Channel['title'] == 'Private Messages')
            $PrivateMessagesID = $Channel['nodeid'];
      }

      if (!$HomeID)
         exit ("Missing node 'Forum'");

      // Go thru the category list 6 times to build a (up to) 6-deep hierarchy
      $CategoryIDs[] = $HomeID;
      for ($i = 0; $i < 6; $i++) {
         foreach ($Channels as $Channel) {
            if (in_array($Channel['nodeid'], $CategoryIDs))
               continue;
            if (in_array($Channel['parentid'], $CategoryIDs))
               $CategoryIDs[] = $Channel['nodeid'];
         }
      }
      // Drop 'Forum' from the tree
      if(($key = array_search($HomeID, $CategoryIDs)) !== false) {
         unset($CategoryIDs[$key]);
      }

      $Category_Map = array(
         'nodeid'=>'CategoryID',
         'title'=>'Name',
         'description'=>'Description',
         'userid'=>'InsertUserID',
         'parentid'=>'ParentCategoryID',
         'urlident'=>'UrlCode',
         'displayorder'=>array('Column'=>'Sort', 'Type'=>'int'),
         'lastcontentid'=>'LastDiscussionID',
         'textcount'=>'CountComments', // ???
         'totalcount'=>'CountDiscussions', // ???
      );

      // Categories are Channels that were found in the Forum tree
      // If parent was 'Forum' set the parent to Root instead (-1)
      $Ex->ExportTable('Category', "select n.*,
         FROM_UNIXTIME(publishdate) as DateInserted,
         if(parentid={$HomeID},-1,parentid) as parentid
      from :_node n
      where nodeid in (".implode(',',$CategoryIDs).")
      ", $Category_Map);


      /// Permission
      //permission - nodeid,(user)groupid, and it gets worse from there.


      // Discussion.
      $Discussion_Map = array(
         'nodeid'=>'DiscussionID',
         'title'=>'Name',
         'userid'=>'InsertUserID',
         'rawtext' => 'Body',
         'parentid'=>'CategoryID',
         'lastcontentid'=>'LastCommentID',
         'lastauthorid'=>'LastCommentUserID',
         // htmlstate - on,off,on_nl2br
         // infraction
         // attach
         // reportnodeid
      );

      $Ex->ExportTable('Discussion', "select n.*,
         t.rawtext,
         FROM_UNIXTIME(publishdate) as DateInserted,
         v.count as CountViews,
         convert(ABS(open-1),char(1)) as Closed,
         if(convert(sticky,char(1))>0,2,0) as Announce
      from :_node n
         left join :_contenttype c on n.contenttypeid = c.contenttypeid
         left join :_nodeview v on v.nodeid = n.nodeid
         left join :_text t on t.nodeid = n.nodeid
      where c.class = 'Text'
         and n.showpublished = 1
         and parentid in (".implode(',',$CategoryIDs).")
      ", $Discussion_Map);


      // UserDiscussion
      $UserDiscussion_Map = array(
         'discussionid'=>'DiscussionID',
         'userid'=>'InsertUserID',
      );
      // Should be able to inner join `discussionread` for DateLastViewed
      // but it's blank in my sample data so I don't trust it.
      $Ex->ExportTable('UserDiscussion', "select s.*,
         1 as Bookmarked,
         NOW() as DateLastViewed
      from :_subscribediscussion s
      ", $UserDiscussion_Map);


      // Comment.
      $Comment_Map = array(
         'nodeid'=>'CommentID',
         'rawtext' => 'Body',
         'userid'=>'InsertUserID',
         'parentid'=>'DiscussionID',
      );

      $Ex->ExportTable('Comment', "select n.*,
         t.rawtext,
         FROM_UNIXTIME(publishdate) as DateInserted
      from :_node n
         left join :_contenttype c on n.contenttypeid = c.contenttypeid
         left join :_text t on t.nodeid = n.nodeid
      where c.class = 'Text'
         and n.showpublished = 1
         and parentid not in (".implode(',',$CategoryIDs).")
      ", $Comment_Map);


      /// Drafts
      // autosavetext table


      /// Poll
      // class='Poll'


      // Media
      $Media_Map = array(
         'nodeid' => 'MediaID',
         'filename' => 'Name',
         'extension' => array('Column' => 'Type', 'Filter' => array($this, 'BuildMimeType')),
         'Path2' => array('Column' => 'Path', 'Filter' => array($this, 'BuildMediaPath')),
         'ThumbPath2' => array('Column' => 'ThumbPath', 'Filter' => array($this, 'BuildMediaPath')),
         'width' => 'ImageWidth',
         'height' => 'ImageHeight',
         'filesize' => 'Size',
      );
      $Ex->ExportTable('Media', "select a.*,
         filename as Path2,
         filename as ThumbPath2,
         FROM_UNIXTIME(f.dateline) as DateInserted,
         f.userid as userid,
         f.userid as InsertUserID,
         if (f.width,f.width,1) as width,
         if (f.height,f.height,1) as height,
         n.parentid as ForeignID,
         f.extension,
         f.filesize,
         'local' as StorageMethod,
         if(n2.parentid in (".implode(',',$CategoryIDs)."),'discussion','comment') as ForeignTable
      from :_attach a
         left join :_node n on n.nodeid = a.nodeid
         left join :_filedata f on f.filedataid = a.filedataid
         left join :_node n2 on n.parentid = n2.nodeid
      where a.visible = 1
      ", $Media_Map);
      // left join :_contenttype c on n.contenttypeid = c.contenttypeid


      // Conversations.
      $Conversation_Map = array(
         'nodeid' => 'ConversationID',
         'userid' => 'InsertUserID',
         'totalcount' => 'CountMessages',
         'title' => 'Subject',
      );
      $Ex->ExportTable('Conversation',
         "select n.*,
            n.nodeid as FirstMessageID,
            FROM_UNIXTIME(n.publishdate) as DateInserted
          from :_node n
            left join :_text t on t.nodeid = n.nodeid
          where parentid = $PrivateMessagesID
            and t.rawtext <> ''", $Conversation_Map);


      // Conversation Messages.
      $ConversationMessage_Map = array(
         'nodeid' => 'MessageID',
         'rawtext' => 'Body',
         'userid' => 'InsertUserID'
      );
      $Ex->ExportTable('ConversationMessage',
         "select n.*,
            t.rawtext,
            'BBCode' as Format,
            if(n.parentid<>$PrivateMessagesID,n.parentid,n.nodeid) as ConversationID,
            FROM_UNIXTIME(n.publishdate) as DateInserted
          from :_node n
            left join :_contenttype c on n.contenttypeid = c.contenttypeid
            left join :_text t on t.nodeid = n.nodeid
          where c.class = 'PrivateMessage'
            and t.rawtext <> ''", $ConversationMessage_Map);


      // User Conversation.
      $UserConversation_Map = array(
         'userid' => 'UserID',
         'nodeid' => 'ConversationID',
         'deleted' => 'Deleted'
      );
      // would be nicer to do an intermediary table to sum s.msgread for uc.CountReadMessages
      $Ex->ExportTable('UserConversation',
         "select s.*
          from :_sentto s
          ;", $UserConversation_Map);


      /// Groups
      // class='SocialGroup'
      // class='SocialGroupDiscussion'
      // class='SocialGroupMessage'


      $Ex->EndExport();
   }
}
?>