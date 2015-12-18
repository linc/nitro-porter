<?php
/**
 * jforum exporter tool.
 *
 * @copyright Vanilla Forums Inc. 2010-2014
 * @license GNU GPL2
 * @package VanillaPorter
 * @see functions.commandline.php for command line usage.
 */

// Add to the $Supported array so it appears in the dropdown menu. Uncomment next line.
$Supported['jforum'] = array('name' => 'jforum', 'prefix' => 'jforum_');
$Supported['jforum']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'PrivateMessages' => 1,
    'Bookmarks' => 1,
    'Signatures' => 1,

);

class Jforum extends ExportController {
    /**
     * You can use this to require certain tables and columns be present.
     *
     * This can be useful for verifying data integrity. Don't specify more columns
     * than your porter actually requires to avoid forwards-compatibility issues.
     *
     * @var array Required tables => columns
     */
    protected $SourceTables = array(
        'forums' => array(), // This just requires the 'forum' table without caring about columns.
        'posts' => array(),
        'posts_text' => array(),
        'topics' => array(),
        'users' => array('user_id', 'username', 'user_email'), // Require specific cols on 'users'
    );

    /**
     * Main export process.
     *
     * @param ExportModel $Ex
     * @see $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function ForumExport($Ex) {

        $CharacterSet = $Ex->GetCharacterSet('posts_text');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        // Reiterate the platform name here to be included in the porter file header.
        $Ex->BeginExport('', 'jforum');


        // User.
        $User_Map = array(
            'user_id' => 'UserID',
            'username' => 'Name',
            'user_email' => 'Email',
            'user_regdate' => 'DateInserted',
            'user_regdate2' => 'DateFirstVisit',
            'user_posts' => 'CountComments', // Approximate until counts are updated
            'user_avatar' => 'Photo',
            'deleted' => 'Deleted',
            'user_from' => 'Location',
            'user_biography' => 'About',
        );
        $Ex->ExportTable('User', "
         select u.*,
            'Reset' as HashMethod,
            user_regdate as user_regdate2
         from :_users u
         ", $User_Map);


        // Role.
        $Role_Map = array(
            'group_id' => 'RoleID',
            'group_name' => 'Name',
            'group_description' => 'Description',
        );
        $Ex->ExportTable('Role', "
         select *
         from :_groups", $Role_Map);


        // User Role.
        $UserRole_Map = array(
            'user_id' => 'UserID',
            'group_id' => 'RoleID',
        );
        $Ex->ExportTable('UserRole', "
         select u.*
         from :_user_groups u", $UserRole_Map);


        // UserMeta.
        $Ex->ExportTable('UserMeta', "
         select user_id as UserID,
            'Profile.Website' as `Name`,
            user_website as `Value`
         from :_users
         where user_website is not null

         union

         select user_id, 'Plugins.Signatures.Sig', user_sig
         from :_users where user_sig is not null

         union

         select user_id, 'Plugins.Signatures.Format', 'BBCode'
         from :_users where user_sig is not null

         union

         select user_id, 'Profile.Occupation', user_occ
         from :_users where user_occ is not null

         union

         select user_id, 'Profile.Interests', user_interests
         from :_users where user_interests is not null
      ");


        // Category.
        // _categories is tier 1, _forum is tier 2.
        // Overlapping IDs, so fast-forward _categories by 1000.
        $Category_Map = array();
        $Ex->ExportTable('Category', "
         select
            c.categories_id+1000 as CategoryID,
            -1 as ParentCategoryID,
            c.title as Name,
            null as Description,
            1 as Depth,
            c.display_order as Sort
         from :_categories c

         union

         select
            f.forum_id as CategoryID,
            categories_id+1000 as ParentCategoryID,
            forum_name as Name,
            forum_desc as Description,
            2 as Depth,
            null as Sort
         from :_forums f
         ", $Category_Map);


        // Discussion.
        $Discussion_Map = array(
            'topic_id' => 'DiscussionID',
            'forum_id' => 'CategoryID',
            'user_id' => 'InsertUserID',
            'topic_time' => 'DateInserted',
            'topic_title' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'topic_views' => 'CountViews',
            'topic_replies' => 'CountComments',
            'topic_status' => 'Closed',
            'topic_type' => 'Announce',
            'post_text' => 'Body',
        );
        // It's easier to convert between Unix time and MySQL datestamps during the db query.
        $Ex->ExportTable('Discussion', "
         select *,
            t.forum_id as forum_id,
            if(t.topic_type>0,1,0) as topic_type,
            'BBCode' as Format
         from :_topics t
         left join :_posts_text p
            on t.topic_first_post_id = p.post_id", $Discussion_Map);


        // Comment.
        $Comment_Map = array(
            'post_id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'user_id' => 'InsertUserID',
            'poster_ip' => 'InsertIPAddress',
            'post_text' => 'Body',
            'post_time' => 'DateInserted',
            'post_edit_time' => 'DateUpdated',
        );
        $Ex->ExportTable('Comment', "
         select p.*, t.post_text, 'BBCode' as Format
         from :_posts p
         left join :_posts_text t
            on p.post_id = t.post_id
         where p.post_id not in (select topic_first_post_id from :_topics)", $Comment_Map);


        // UserDiscussion.
        // Guessing table is called "_watch" because they are all bookmarks.
        $UserDiscussion_Map = array(
            'topic_id' => 'DiscussionID',
            'user_id' => 'UserID',
        );
        $Ex->ExportTable('UserDiscussion', "
         select *,
            1 as Bookmarked,
            if(is_read,NOW(),null) as DateLastViewed
         from :_topics_watch w", $UserDiscussion_Map);


        // Conversation.
        // Thread using tmp table based on the pair of users talking.
        $Ex->Query('drop table if exists z_conversation;');
        $Ex->Query('create table z_conversation (
        ConversationID int unsigned NOT NULL AUTO_INCREMENT,
        LowUserID int unsigned,
        HighUserID int unsigned,
        PRIMARY KEY (ConversationID)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;');
        $Ex->Query('insert into z_conversation (LowUserID, HighUserID)
         select least(privmsgs_from_userid, privmsgs_to_userid),
            greatest(privmsgs_from_userid, privmsgs_to_userid)
         from :_privmsgs
         group by least(privmsgs_from_userid, privmsgs_to_userid),
            greatest(privmsgs_from_userid, privmsgs_to_userid)');
        // Replying on /dba/counts to rebuild most of this data later.
        $Conversation_Map = array(
            'privmsgs_from_userid' => 'InsertUserID',
            'privmsgs_date' => 'DateInserted',
            'privmsgs_subject' => 'Subject',
        );
        $Ex->ExportTable('Conversation', "
         select p.*, c.ConversationID
         from :_privmsgs p
         left join z_conversation c on c.HighUserID = greatest(p.privmsgs_from_userid, p.privmsgs_to_userid)
            and c.LowUserID = least(p.privmsgs_from_userid, p.privmsgs_to_userid)
         group by least(privmsgs_from_userid, privmsgs_to_userid),
            greatest(privmsgs_from_userid, privmsgs_to_userid)", $Conversation_Map);


        // Conversation Message.
        // Messages with the same timestamps are sent/received copies.
        // Yes that'd probably break down on huge sites but it's too convenient to pass up for now.
        $Message_Map = array(
            'privmsgs_id' => 'MessageID',
            'privmsgs_from_userid' => 'InsertUserID',
            'privmsgs_date' => 'DateInserted',
            //'privmsgs_subject' => 'Subject',
            'privmsgs_text' => 'Body',
        );
        $Ex->ExportTable('ConversationMessage', "
         select *, c.ConversationID, 'BBCode' as Format
         from :_privmsgs p
         left join :_privmsgs_text t on t.privmsgs_id = p.privmsgs_id
         left join z_conversation c on c.LowUserID = least(privmsgs_from_userid, privmsgs_to_userid)
            and c.HighUserID = greatest(privmsgs_from_userid, privmsgs_to_userid)
         group by privmsgs_date", $Message_Map);


        // UserConversation
        $Ex->ExportTable('UserConversation', "
         select ConversationID, LowUserID as UserID, NOW() as DateLastViewed from z_conversation
         union
         select ConversationID, HighUserID as UserID, NOW() as DateLastViewed from z_conversation
         ");
        // Needs afterward: update GDN_UserConversation set CountReadMessages = (select count(MessageID) from GDN_ConversationMessage where GDN_ConversationMessage.ConversationID = GDN_UserConversation.ConversationID)


        $Ex->EndExport();
    }
}
