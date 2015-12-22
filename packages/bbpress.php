<?php
/**
 * bbPress exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['bbpress'] = array('name' => 'bbPress 1', 'prefix' => 'bb_');
$Supported['bbpress']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'PrivateMessages' => 1,
    'Passwords' => 1,
);

class BBPress extends ExportController {
    /** @var array Required tables => columns */
    protected $SourceTables = array(
        'forums' => array(),
        'posts' => array(),
        'topics' => array(),
        'users' => array('ID', 'user_login', 'user_pass', 'user_email', 'user_registered'),
        'meta' => array()
    );

    /**
     * Forum-specific export format.
     * @param ExportModel $Ex
     */
    protected function forumExport($Ex) {

        $CharacterSet = $Ex->getCharacterSet('posts');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        // Begin
        $Ex->beginExport('', 'bbPress 1.*', array('HashMethod' => 'Vanilla'));

        // Users
        $User_Map = array(
            'ID' => 'UserID',
            'user_login' => 'Name',
            'user_pass' => 'Password',
            'user_email' => 'Email',
            'user_registered' => 'DateInserted'
        );
        $Ex->exportTable('User', "select * from :_users", $User_Map);  // ":_" will be replace by database prefix

        // Roles
        $Ex->exportTable('Role',
            "select 1 as RoleID, 'Guest' as Name
         union select 2, 'Key Master'
         union select 3, 'Administrator'
         union select 4, 'Moderator'
         union select 5, 'Member'
         union select 6, 'Inactive'
         union select 7, 'Blocked'");

        // UserRoles
        $UserRole_Map = array(
            'user_id' => 'UserID'
        );
        $Ex->exportTable('UserRole',
            "select distinct
           user_id,
           case when locate('keymaster', meta_value) <> 0 then 2
           when locate('administrator', meta_value) <> 0 then 3
           when locate('moderator', meta_value) <> 0 then 4
           when locate('member', meta_value) <> 0 then 5
           when locate('inactive', meta_value) <> 0 then 6
           when locate('blocked', meta_value) <> 0 then 7
           else 1 end as RoleID
         from :_usermeta
         where meta_key = 'bb_capabilities'", $UserRole_Map);

        // Categories
        $Category_Map = array(
            'forum_id' => 'CategoryID',
            'forum_name' => 'Name',
            'forum_desc' => 'Description',
            'forum_slug' => 'UrlCode',
            'left_order' => 'Sort'
        );
        $Ex->exportTable('Category', "select *,
         lower(forum_slug) as forum_slug,
         nullif(forum_parent,0) as ParentCategoryID
         from :_forums", $Category_Map);

        // Discussions
        $Discussion_Map = array(
            'topic_id' => 'DiscussionID',
            'forum_id' => 'CategoryID',
            'topic_poster' => 'InsertUserID',
            'topic_title' => 'Name',
            'Format' => 'Format',
            'topic_start_time' => 'DateInserted',
            'topic_sticky' => 'Announce'
        );
        $Ex->exportTable('Discussion', "select t.*,
            'Html' as Format,
            case t.topic_open when 0 then 1 else 0 end as Closed
         from :_topics t
         where topic_status = 0", $Discussion_Map);

        // Comments
        $Comment_Map = array(
            'post_id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'post_text' => array('Column' => 'Body', 'Filter' => 'bbPressTrim'),
            'Format' => 'Format',
            'poster_id' => 'InsertUserID',
            'post_time' => 'DateInserted'
        );
        $Ex->exportTable('Comment', "select p.*,
            'Html' as Format
         from :_posts p
         where post_status = 0", $Comment_Map);

        // Conversations.

        // The export is different depending on the table layout.
        $PM = $Ex->exists('bbpm', array('ID', 'pm_title', 'pm_from', 'pm_to', 'pm_text', 'sent_on', 'pm_thread'));
        $ConversationVersion = '';

        if ($PM === true) {
            // This is from an old version of the plugin.
            $ConversationVersion = 'old';
        } elseif (is_array($PM) && count(array_intersect(array('ID', 'pm_from', 'pm_text', 'sent_on', 'pm_thread'),
                $PM)) == 0
        ) {
            // This is from a newer version of the plugin.
            $ConversationVersion = 'new';
        }

        if ($ConversationVersion) {
            // Conversation.
            $Conv_Map = array(
                'pm_thread' => 'ConversationID',
                'pm_from' => 'InsertUserID'
            );
            $Ex->exportTable('Conversation',
                "select *, from_unixtime(sent_on) as DateInserted
            from :_bbpm
            where thread_depth = 0", $Conv_Map);

            // ConversationMessage.
            $ConvMessage_Map = array(
                'ID' => 'MessageID',
                'pm_thread' => 'ConversationID',
                'pm_from' => 'InsertUserID',
                'pm_text' => array('Column' => 'Body', 'Filter' => 'bbPressTrim')
            );
            $Ex->exportTable('ConversationMessage',
                'select *, from_unixtime(sent_on) as DateInserted
            from :_bbpm', $ConvMessage_Map);

            // UserConversation.
            $Ex->query("create temporary table bbpmto (UserID int, ConversationID int)");

            if ($ConversationVersion == 'new') {
                $To = $Ex->query("select object_id, meta_value from :_meta where object_type = 'bbpm_thread' and meta_key = 'to'",
                    true);
                if (is_resource($To)) {
                    while (($Row = @mysql_fetch_assoc($To)) !== false) {
                        $Thread = $Row['object_id'];
                        $Tos = explode(',', trim($Row['meta_value'], ','));
                        $ToIns = '';
                        foreach ($Tos as $ToID) {
                            $ToIns .= "($ToID,$Thread),";
                        }
                        $ToIns = trim($ToIns, ',');

                        $Ex->query("insert bbpmto (UserID, ConversationID) values $ToIns", true);
                    }
                    mysql_free_result($To);

                    $Ex->exportTable('UserConversation', 'select * from bbpmto');
                }
            } else {
                $ConUser_Map = array(
                    'pm_thread' => 'ConversationID',
                    'pm_from' => 'UserID'
                );
                $Ex->exportTable('UserConversation',
                    'select distinct
                 pm_thread,
                 pm_from,
                 del_sender as Deleted
               from :_bbpm

               union

               select distinct
                 pm_thread,
                 pm_to,
                 del_reciever
               from :_bbpm', $ConUser_Map);
            }
        }

        // End
        $Ex->endExport();
    }
}

function bbPressTrim($Text) {
    return rtrim(bb_Code_Trick_Reverse($Text));
}

function bb_Code_Trick_Reverse($text) {
    $text = preg_replace_callback("!(<pre><code>|<code>)(.*?)(</code></pre>|</code>)!s", 'bb_decodeit', $text);
    $text = str_replace(array('<p>', '<br />'), '', $text);
    $text = str_replace('</p>', "\n", $text);
    $text = str_replace('<coded_br />', '<br />', $text);
    $text = str_replace('<coded_p>', '<p>', $text);
    $text = str_replace('</coded_p>', '</p>', $text);

    return $text;
}

function bb_Decodeit($matches) {
    $text = $matches[2];
    $trans_table = array_flip(get_html_translation_table(HTML_ENTITIES));
    $text = strtr($text, $trans_table);
    $text = str_replace('<br />', '<coded_br />', $text);
    $text = str_replace('<p>', '<coded_p>', $text);
    $text = str_replace('</p>', '</coded_p>', $text);
    $text = str_replace(array('&#38;', '&amp;'), '&', $text);
    $text = str_replace('&#39;', "'", $text);
    if ('<pre><code>' == $matches[1]) {
        $text = "\n$text\n";
    }

    return "`$text`";
}

// Closing PHP tag required. (make.php)
?>
