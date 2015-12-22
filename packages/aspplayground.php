<?php
/**
 * ASP Playground exporter tool
 *
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['apg'] = array('name' => 'ASP Playground', 'prefix' => 'pgd_');
$supported['apg']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
);

class APG extends ExportController {
    /**
     * @param ExportModel $Ex
     */
    public function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('Threads');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->beginExport('', 'ASP Playground');
        $ex->sourcePrefix = 'pgd_';

        // User.
        $user_Map = array(
            'Mem' => 'UserID',
            'Login' => 'Name',
            'Email' => 'Email',
            'Userpass' => 'Password',
            'totalPosts' => 'CountComments',
            'ip' => 'LastIPAddress',
            'banned' => 'Banned',
            'dateSignUp' => 'DateInserted',
            'lastLogin' => 'DateLastActive',
        );
        $ex->exportTable('User', "
         select m.*,
            'Text' as HashMethod
         from :_Members m;", $user_Map);

        // Role.
        /*$Role_Map = array(
            'GroupID' => 'RoleID',
            'Name' => 'Name');
        $Ex->ExportTable('Role', "
           select *
           from yaf_Group;", $Role_Map);
        */

        // UserRole.
        // Make everyone a member since there's no used roles.
        $userRole_Map = array(
            'Mem' => 'UserID'
        );
        $ex->exportTable('UserRole', 'select Mem, 8 as RoleID from :_Members', $userRole_Map);

        // Signatures.
        $ex->exportTable('UserMeta', "
         select
            Mem,
            'Plugin.Signatures.Sig' as `Name`,
            signature as `Value`
         from :_Members
         where signature <> ''

         union all

         select
            Mem,
            'Plugin.Signatures.Format' as `Name`,
            'BBCode' as `Value`
         from :_Members
         where signature <> '';");

        // Category.
        $category_Map = array(
            'ForumID' => 'CategoryID',
            'ForumTitle' => 'Name',
            'ForumDesc' => 'Description',
            'Sort' => 'Sort',
            'lastModTime' => 'DateUpdated'
        );

        $ex->exportTable('Category', "
         select f.*
         from :_Forums f;", $category_Map);

        // Discussion.
        $discussion_Map = array(
            'messageID' => 'DiscussionID',
            'ForumID' => 'CategoryID',
            'mem' => 'InsertUserID',
            'dateCreated' => 'DateInserted',
            'Subject' => 'Name',
            'hits' => 'CountViews',
            'lastupdate' => 'DateLastComment'
        );
        $ex->exportTable('Discussion', "
         select
            t.*,
            m.Body
         from :_Threads t
         left join :_Messages m on m.messageID = t.messageID
         ;", $discussion_Map);

        // Comment.
        $comment_Map = array(
            'messageID' => 'CommentID',
            'threadID' => 'DiscussionID',
            'parent' => array('Column' => 'ReplyToCommentID', 'Type' => 'int'),
            'Mem' => 'InsertUserID',
            'dateCreated' => 'DateInserted',
            'Body' => 'Body',
            'ip' => 'InsertIPAddress'
        );
        $ex->exportTable('Comment', "
         select m.*,
            'BBCode' as Format
         from :_Messages m;", $comment_Map);

        /*
        // Conversation.
        $this->_ExportConversationTemps();

        $Conversation_Map = array(
            'PMessageID' => 'ConversationID',
            'FromUserID' => 'InsertUserID',
            'Created' => 'DateInserted',
            'Title' => array('Column' => 'Subject', 'Type' => 'varchar(512)')
            );
        $Ex->ExportTable('Conversation', "
           select
              pm.*,
              g.Title
           from z_pmgroup g
           join yaf_PMessage pm
              on g.Group_ID = pm.PMessageID;", $Conversation_Map);

        // UserConversation.
        $UserConversation_Map = array(
            'PM_ID' => 'ConversationID',
            'User_ID' => 'UserID',
            'Deleted' => 'Deleted');
        $Ex->ExportTable('UserConversation', "
           select pto.*
           from z_pmto pto
           join z_pmgroup g
              on pto.PM_ID = g.Group_ID;", $UserConversation_Map);

        // ConversationMessage.
        $ConversationMessage_Map = array(
            'PMessageID' => 'MessageID',
            'Group_ID' => 'ConversationID',
            'FromUserID' => 'InsertUserID',
            'Created' => 'DateInserted',
            'Body' => 'Body',
            'Format' => 'Format');
        $Ex->ExportTable('ConversationMessage', "
           select
              pm.*,
              case when pm.Flags & 1 = 1 then 'Html' else 'BBCode' end as Format,
              t.Group_ID
           from yaf_PMessage pm
           join z_pmtext t
              on t.PM_ID = pm.PMessageID;", $ConversationMessage_Map);
        */

        $ex->endExport();
    }

    public function cleanDate($value) {
        if (!$value) {
            return null;
        }
        if (substr($value, 0, 4) == '0000') {
            return null;
        }

        return $value;
    }

}

// Closing PHP tag required. (make.php)
?>
