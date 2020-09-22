<?php
/**
 * phpBB exporter tool
 *
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['phpbb2'] = array('name' => 'phpBB 2', 'prefix' => 'phpbb_');
$supported['phpbb2']['features'] = array(
    'Users' => 1,
    'Passwords' => 1,
    'Categories' => 1,
    'Discussions' => 1,
    'Comments' => 1,
    'Polls' => 0,
    'Roles' => 1,
    'Avatars' => 0,
    'PrivateMessages' => 1,
    'Signatures' => 0,
    'Attachments' => 0,
    'Bookmarks' => 0,
    'Permissions' => 0,
    'Badges' => 0,
    'UserNotes' => 0,
    'Ranks' => 0,
    'Groups' => 0,
    'Tags' => 0,
    'UserTags' => 0,
    'Reactions' => 0,
    'Articles' => 0,
);

class PhpBB2 extends ExportController {

    /** @var array Required tables => columns */
    protected $sourceTables = array(
        'users' => array(
            'user_id',
            'username',
            'user_password',
            'user_email',
            'user_timezone',
            'user_posts',
            'user_regdate',
            'user_lastvisit'
        ),
        'groups' => array('group_id', 'group_name', 'group_description'),
        'user_group' => array('user_id', 'group_id'),
        'forums' => array('forum_id', 'forum_name', 'forum_desc', 'forum_order'),
        'topics' => array(
            'topic_id',
            'forum_id',
            'topic_poster',
            'topic_title',
            'topic_views',
            'topic_first_post_id',
            'topic_status',
            'topic_type',
            'topic_time'
        ),
        'posts' => array('post_id', 'topic_id', 'poster_id', 'post_time', 'post_edit_time'),
        'posts_text' => array('post_id', 'post_text'),
        'privmsgs' => array(
            'privmsgs_id',
            'privmsgs_subject',
            'privmsgs_from_userid',
            'privmsgs_to_userid',
            'privmsgs_date'
        ),
        'privmsgs_text' => array('privmsgs_text_id', 'privmsgs_bbcode_uid', 'privmsgs_text')
    );

    /**
     * Forum-specific export format.
     * @param ExportModel $ex
     */
    protected function forumExport($ex) {

        $characterSet = $ex->getCharacterSet('posts_text');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        $ex->sourcePrefix = 'phpbb_';

        // Begin
        $ex->beginExport('', 'phpBB 2.*', array('HashMethod' => 'phpBB'));

        // Users
        $user_Map = array(
            'user_id' => 'UserID',
            'username' => 'Name',
            'user_password' => 'Password',
            'user_email' => 'Email',
            'user_timezone' => 'HourOffset',
            'user_posts' => array('Column' => 'CountComments', 'Type' => 'int')
        );
        $ex->exportTable('User', "select *,
            FROM_UNIXTIME(nullif(user_regdate, 0)) as DateFirstVisit,
            FROM_UNIXTIME(nullif(user_lastvisit, 0)) as DateLastActive,
            FROM_UNIXTIME(nullif(user_regdate, 0)) as DateInserted
         from :_users", $user_Map);  // ":_" will be replace by database prefix


        // Roles
        $role_Map = array(
            'group_id' => 'RoleID',
            'group_name' => 'Name',
            'group_description' => 'Description'
        );
        // Skip single-user groups
        $ex->exportTable('Role', 'select * from :_groups where group_single_user = 0', $role_Map);


        // UserRoles
        $userRole_Map = array(
            'user_id' => 'UserID',
            'group_id' => 'RoleID'
        );
        // Skip pending memberships
        $ex->exportTable('UserRole', '
            select
                user_id,
                group_id
            from :_user_group
            where user_pending = 0
        ;', $userRole_Map);

        // Categories
        $category_Map = array(
            'id' => 'CategoryID',
            'cat_title' => 'Name',
            'description' => 'Description',
            'parentid' => 'ParentCategoryID'
        );
        $ex->exportTable('Category',
            "select
  c.cat_id * 1000 as id,
  c.cat_title,
  c.cat_order * 1000 as Sort,
  null as parentid,
  '' as description
from :_categories c

union all

select
  f.forum_id,
  f.forum_name,
  c.cat_order * 1000 + f.forum_order,
  c.cat_id * 1000 as parentid,
  f.forum_desc
from :_forums f
left join :_categories c
  on f.cat_id = c.cat_id", $category_Map);

        // Discussions
        $discussion_Map = array(
            'topic_id' => 'DiscussionID',
            'forum_id' => 'CategoryID',
            'topic_poster' => 'InsertUserID',
            'topic_title' => 'Name',
            'Format' => 'Format',
            'topic_views' => 'CountViews'
        );
        $ex->exportTable('Discussion', "select t.*,
        'BBCode' as Format,
         case t.topic_status when 1 then 1 else 0 end as Closed,
         case t.topic_type when 1 then 2 else 0 end as Announce,
         FROM_UNIXTIME(t.topic_time) as DateInserted
        from :_topics t",
            $discussion_Map);

        // Comments
        $comment_Map = array(
            'post_id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'post_text' => array('Column' => 'Body', 'Filter' => array($this, 'removeBBCodeUIDs')),
            'Format' => 'Format',
            'poster_id' => 'InsertUserID'
        );
        $ex->exportTable('Comment', "select p.*, pt.post_text, pt.bbcode_uid,
        'BBCode' as Format,
         FROM_UNIXTIME(p.post_time) as DateInserted,
         FROM_UNIXTIME(nullif(p.post_edit_time,0)) as DateUpdated
         from :_posts p inner join :_posts_text pt on p.post_id = pt.post_id",
            $comment_Map);

        // Conversations tables.
        $ex->query("drop table if exists z_pmto;");

        $ex->query("create table z_pmto (
id int unsigned,
userid int unsigned,
primary key(id, userid));");

        $ex->query("insert ignore z_pmto (id, userid)
select privmsgs_id, privmsgs_from_userid
from :_privmsgs;");

        $ex->query("insert ignore z_pmto (id, userid)
select privmsgs_id, privmsgs_to_userid
from :_privmsgs;");

        $ex->query("drop table if exists z_pmto2;");

        $ex->query("create table z_pmto2 (
  id int unsigned,
  userids varchar(250),
  primary key (id)
);");

        $ex->query("insert ignore z_pmto2 (id, userids)
select
  id,
  group_concat(userid order by userid)
from z_pmto
group by id;");

        $ex->query("drop table if exists z_pm;");

        $ex->query("create table z_pm (
  id int unsigned,
  subject varchar(255),
  subject2 varchar(255),
  userids varchar(250),
  groupid int unsigned
);");

        $ex->query("insert z_pm (
  id,
  subject,
  subject2,
  userids
)
select
  pm.privmsgs_id,
  pm.privmsgs_subject,
  case when pm.privmsgs_subject like 'Re: %' then trim(substring(pm.privmsgs_subject, 4)) else pm.privmsgs_subject end as subject2,
  t.userids
from :_privmsgs pm
join z_pmto2 t
  on t.id = pm.privmsgs_id;");

        $ex->query("create index z_idx_pm on z_pm (id);");

        $ex->query("drop table if exists z_pmgroup;");

        $ex->query("create table z_pmgroup (
  groupid int unsigned,
  subject varchar(255),
  userids varchar(250)
);");

        $ex->query("insert z_pmgroup (
  groupid,
  subject,
  userids
)
select
  min(pm.id),
  pm.subject2,
  pm.userids
from z_pm pm
group by pm.subject2, pm.userids;");

        $ex->query("create index z_idx_pmgroup on z_pmgroup (subject, userids);");
        $ex->query("create index z_idx_pmgroup2 on z_pmgroup (groupid);");

        $ex->query("update z_pm pm
join z_pmgroup g
  on pm.subject2 = g.subject and pm.userids = g.userids
set pm.groupid = g.groupid;");

        // Conversations.
        $conversation_Map = array(
            'privmsgs_id' => 'ConversationID',
            'privmsgs_from_userid' => 'InsertUserID',
            'RealSubject' => array(
                'Column' => 'Subject',
                'Type' => 'varchar(250)',
                'Filter' => array('Phpbb2', 'EntityDecode')
            )
        );

        $ex->exportTable('Conversation', "select
  g.subject as RealSubject,
  pm.*,
  from_unixtime(pm.privmsgs_date) as DateInserted
from :_privmsgs pm
join z_pmgroup g
  on g.groupid = pm.privmsgs_id", $conversation_Map);

        // Coversation Messages.
        $conversationMessage_Map = array(
            'privmsgs_id' => 'MessageID',
            'groupid' => 'ConversationID',
            'privmsgs_text' => array('Column' => 'Body', 'Filter' => array($this, 'removeBBCodeUIDs')),
            'privmsgs_from_userid' => 'InsertUserID'
        );
        $ex->exportTable('ConversationMessage',
            "select
         pm.*,
         txt.*,
         txt.privmsgs_bbcode_uid as bbcode_uid,
         pm2.groupid,
         'BBCode' as Format,
         FROM_UNIXTIME(pm.privmsgs_date) as DateInserted
       from :_privmsgs pm
       join :_privmsgs_text txt
         on pm.privmsgs_id = txt.privmsgs_text_id
       join z_pm pm2
         on pm.privmsgs_id = pm2.id", $conversationMessage_Map);

        // User Conversation.
        $userConversation_Map = array(
            'userid' => 'UserID',
            'groupid' => 'ConversationID'
        );
        $ex->exportTable('UserConversation',
            "select
         g.groupid,
         t.userid
       from z_pmto t
       join z_pmgroup g
         on g.groupid = t.id;", $userConversation_Map);

        $ex->query('drop table if exists z_pmto');
        $ex->query('drop table if exists z_pmto2;');
        $ex->query('drop table if exists z_pm;');
        $ex->query('drop table if exists z_pmgroup;');

        // Media
        $ex->exportTable('Media', "
            select
                ad.attach_id as MediaID,
                ad.real_filename as Name,
                concat('attachments/',ad.physical_filename) as Path,
                concat('attachments/',ad.physical_filename) as ThumbPath,
                if(ad.mimetype = '', 'application/octet-stream', ad.mimetype) as Type,
                ad.filesize as Size,
                FROM_UNIXTIME(ad.filetime) as DateInserted,
                ifnull(t.topic_id, a.post_id) as ForeignID,
                if(t.topic_id is not null, 'discussion', 'comment') as ForeignTable,
                a.user_id_1 as InsertUserID
            from :_attachments_desc ad
            inner join :_attachments a on a.attach_id = ad.attach_id
            left join :_topics t on t.topic_first_post_id = a.post_id
        ");

        // End
        $ex->endExport();
    }

    public static function entityDecode($value) {
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }

    public function removeBBCodeUIDs($value, $field, $row) {
        $UID = $row['bbcode_uid'];

        return str_replace(':' . $UID, '', $value);
    }
}

// Closing PHP tag required. (make.php)
?>
