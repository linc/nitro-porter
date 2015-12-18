<?php
/**
 * NodeBB exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['nodebb'] = array('name' => 'NodeBB 0.*', 'prefix' => 'gdn_');
$Supported['nodebb']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'PrivateMessages' => 1,
    'UserWall' => 1,
    'UserNotes' => 1,
    'Bookmarks' => 1,
    'Signatures' => 1,
    'Passwords' => 1,
    'Reactions' => 1
);

class Nodebb extends ExportController {

    /**
     * @param ExportModel $Ex
     */
    protected function ForumExport($Ex) {

        $CharacterSet = $Ex->GetCharacterSet('topic');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        $Ex->BeginExport('', 'NodeBB 0.*', array('HashMethod' => 'Vanilla'));

        // Users
        $User_Map = array(
            'uid' => 'UserID',
            'username' => 'Name',
            'password' => 'Password',
            'email' => 'Email',
            'confirmed' => 'Confirmed',
            'showemail' => 'ShowEmail',
            'joindate' => array('Column' => 'DateInserted', 'Filter' => array($this, 'tsToDate')),
            'lastonline' => array('Column' => 'DateLastActive', 'Filter' => array($this, 'tsToDate')),
            'lastposttime' => array('Column' => 'DateUpdated', 'Filter' => array($this, 'tsToDate')),
            'banned' => 'Banned',
            'admin' => 'Admin',
            'hm' => 'HashMethod'
        );

        $Ex->ExportTable('User', "

             select uid, username, password, email, `email:confirmed` as confirmed, showemail, joindate, lastonline, lastposttime, banned, 0 as admin, 'crypt' as hm
             from :_user

             ", $User_Map);

        //Roles
        $Role_Map = array(
            '_num' => 'RoleID',
            '_key' => array('Column' => 'Name', 'Filter' => array($this, 'roleNameFromKey')),
            'description' => 'Description'
        );

        $Ex->ExportTable('Role', "

            select gm._key as _key, gm._num as _num, g.description as description
            from :_group_members gm left join :_group g
            on gm._key like concat(g._key, '%')

            ", $Role_Map);

        $UserRole_Map = array(
            'id' => 'RoleID',
            'members' => 'UserID'
        );

        $Ex->ExportTable('UserRole', "

            select *, g._num as id
            from :_group_members g join :_group_members__members m
            on g._id = m._parentid

        ", $UserRole_Map);

        // Signatutes.
        $UserMeta_Map = array(
            'uid' => 'UserID',
            'name' => 'Name',
            'signature' => 'Value'
        );

        $Ex->ExportTable('UserMeta', "

            select uid, 'Plugin.Signatures.Sig' as name, signature
            from :_user
            where length(signature) > 1

            union

            select uid, 'Plugin.Signatures.Format', 'Markdown'
            from :_user
            where length(signature) > 1

            union

            select uid, 'Profile.Website' as name, website
            from :_user
            where length(website) > 7

            union

            select uid, 'Profile.Location' as name, location
            from :_user
            where length(location) > 1

        ", $UserMeta_Map);

        // Categories
        $Category_Map = array(
            'cid' => 'CategoryID',
            'name' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'description' => 'Description',
            'order' => 'Sort',
            'parentCid' => 'ParentCategoryID',
            'slug' => array('Column' => 'UrlCode', 'Filter' => array($this, 'removeNumId')),
            'image' => 'Photo',
            'disabled' => 'Archived'
        );

        $Ex->ExportTable('Category', "

            select *
            from :_category

        ", $Category_Map);

        if (!$Ex->IndexExists('z_idx_topic', ':_topic')) {
            $Ex->Query("create index z_idx_topic on :_topic(mainPid);");
        }
        if (!$Ex->IndexExists('z_idx_post', ':_post')) {
            $Ex->Query("create index z_idx_post on :_post(pid);");
        }
        if (!$Ex->IndexExists('z_idx_poll', ':_poll')) {
            $Ex->Query("create index z_idx_poll on :_poll(tid);");
        }

        $Ex->Query("drop table if exists z_discussionids;");
        $Ex->Query("

            create table z_discussionids (
                tid int unsigned,
                primary key(tid)
            );

        ");

        $Ex->Query("

            insert ignore z_discussionids (
                tid
            )
            select mainPid
            from :_topic
            where mainPid is not null
            and deleted != 1;

        ");

        $Ex->Query("drop table if exists z_reactiontotalsupvote;");
        $Ex->Query("

            create table z_reactiontotalsupvote (
                value varchar(50),
                total int,
                primary key (value)
            );

        ");

        $Ex->Query("drop table if exists z_reactiontotalsdownvote;");
        $Ex->Query("

            create table z_reactiontotalsdownvote (
                value varchar(50),
                total int,
                primary key (value)
            );

        ");

        $Ex->Query("drop table if exists z_reactiontotals;");
        $Ex->Query("

            create table z_reactiontotals (
              value varchar(50),
              upvote int,
              downvote int,
              primary key (value)
            );

        ");

        $Ex->Query("

            insert z_reactiontotalsupvote
            select value, count(*) as totals
            from :_uid_upvote
            group by value;

        ");

        $Ex->Query("

            insert z_reactiontotalsdownvote
            select value, count(*) as totals
            from :_uid_downvote
            group by value;

        ");

        $Ex->Query("

            insert z_reactiontotals
            select *
            from (
                select u.value, u.total as up, d.total as down
                from z_reactiontotalsupvote u
                left join z_reactiontotalsdownvote d
                on u.value = d.value

                union

                select d.value, u.total as up, d.total as down
                from z_reactiontotalsdownvote d
                left join z_reactiontotalsupvote u
                on u.value = d.value
            ) as reactions

        ");

        //Discussions
        $Discussion_Map = array(
            'tid' => 'DiscussionID',
            'cid' => 'CategoryID',
            'title' => 'Name',
            'content' => 'Body',
            'uid' => 'InsertUserID',
            'locked' => 'Closed',
            'pinned' => 'Announce',
            'timestamp' => array('Column' => 'DateInserted', 'Filter' => array($this, 'tsToDate')),
            'edited' => array('Column' => 'DateUpdated', 'Filter' => array($this, 'tsToDate')),
            'editor' => 'UpdateUserID',
            'viewcount' => 'CountViews',
            'format' => 'Format',
            'votes' => 'Score',
            'attributes' => array('Column' => 'Attributes', 'Filter' => array($this, 'serializeReactions')),
            'poll' => array('Column' => 'Type', 'Filter' => array($this, 'isPoll'))
        );

        $Ex->ExportTable('Discussion', "

            select p.tid, cid, title, content, p.uid, locked, pinned, p.timestamp, p.edited, p.editor, viewcount, votes, poll._id as poll, 'Markdown' as format, concat(ifnull(u.total, 0), ':', ifnull(d.total, 0)) as attributes
            from :_topic t
            left join :_post p
            on t.mainPid = p.pid
            left join z_reactiontotalsupvote u
            on u.value = t.mainPid
            left join z_reactiontotalsdownvote d
            on d.value = t.mainPid
            left join :_poll poll
            on p.tid = poll.tid
            where t.deleted != 1

        ", $Discussion_Map);

        $Ex->Query("drop table if exists z_comments;");
        $Ex->Query("

            create table z_comments (
                pid int,
                content text,
                uid varchar(255),
                tid varchar(255),
                timestamp double,
                edited varchar(255),
                editor varchar(255),
                votes int,
                upvote int,
                downvote int,
                primary key(pid)
            );

        ");

        $Ex->Query("

            insert ignore z_comments (
                pid,
                content,
                uid,
                tid,
                timestamp,
                edited,
                editor,
                votes
            )
            select p.pid, p.content, p.uid, p.tid, p.timestamp, p.edited, p.editor, p.votes
            from :_post p
            left join z_discussionids t
            on t.tid = p.pid
            where p.deleted != 1 and t.tid is null;

        ");

        $Ex->Query("

            update z_comments as c
            join z_reactiontotals r
            on r.value = c.pid
            set c.upvote = r.upvote, c.downvote = r.downvote;

        ");

        // Comments
        $Comment_Map = array(
            'content' => 'Body',
            'uid' => 'InsertUserID',
            'tid' => 'DiscussionID',
            'timestamp' => array('Column' => 'DateInserted', 'Filter' => array($this, 'tsToDate')),
            'edited' => array('Column' => 'DateUpdated', 'Filter' => array($this, 'tsToDate')),
            'editor' => 'UpdateUserID',
            'votes' => 'Score',
            'format' => 'Format',
            'attributes' => array('Column' => 'Attributes', 'Filter' => array($this, 'serializeReactions'))
        );

        $Ex->ExportTable('Comment', "

            select content, uid, tid, timestamp, edited, editor, votes, 'Markdown' as format, concat(ifnull(upvote, 0), ':', ifnull(downvote, 0)) as attributes
            from z_comments

        ", $Comment_Map);

        //Polls
        $Poll_Map = array(
            'pollid' => 'PollID',
            'title' => 'Name',
            'tid' => 'DiscussionID',
            'votecount' => 'CountVotes',
            'uid' => 'InsertUserID',
            'timestamp' => array('Column' => 'DateInserted', 'Filter' => array($this, 'tsToDate'))
        );

        $Ex->ExportTable('Poll', "

            select *
            from :_poll p left join :_poll_settings ps
            on ps._key like concat(p._key, ':', '%')

        ", $Poll_Map);

        $PollOption_Map = array(
            '_num' => 'PollOptionID',
            '_key' => array('Column' => 'PollID', 'Filter' => array($this, 'idFromKey')),
            'title' => 'Body',
            'sort' => 'Sort',
            'votecount' => array('Column' => 'CountVotes', 'Filter' => array($this, 'makeNullZero')),
            'format' => 'Format'
        );

        $Ex->ExportTable('PollOption', "

            select _num, _key, title, id+1 as sort, votecount, 'Html' as format
            from :_poll_options
            where title is not null

        ", $PollOption_Map);

        $PollVote_Map = array(
            'userid' => 'UserID',
            'poll_option_id' => 'PollOptionID'
        );

        $Ex->ExportTable('PollVote', "

            select povm.members as userid, po._num as poll_option_id
            from :_poll_options_votes__members povm
            left join :_poll_options_votes pov
            on povm._parentid = pov._id
            left join :_poll_options po
            on pov._key like concat(po._key, ':', '%')
            where po.title is not null

        ", $PollVote_Map);

        //Tags
        if (!$Ex->IndexExists('z_idx_topic_key', ':_topic')) {
            $Ex->Query("create index z_idx_topic_key on :_topic (_key);");
        }

        $Tag_Map = array(
            'slug' => array('Column' => 'Name', 'Filter' => array($this, 'nameToSlug')),
            'fullname' => 'FullName',
            'count' => 'CountDiscussions',
            'tagid' => 'TagID',
            'cid' => 'CategoryID',
            'type' => 'Type',
            'timestamp' => array('Column' => 'DateInserted', 'Filter' => array($this, 'tsToDate')),
            'uid' => 'InsertUserID'
        );

        $Now = time();

        $Ex->Query("set @rownr=1000;");

        $Ex->ExportTable('Tag', "

            select @rownr:=@rownr+1 as tagid, members as fullname, members as slug, '' as type, count, timestamp, uid, cid
            from (
                select members, count(*) as count, _parentid
                from :_topic_tags__members
                group by members
            ) as tags
            join :_topic_tags tt
            on tt._id = _parentid
            left join :_topic t
            on substring(tt._key, 1, length(tt._key) - 5) = t._key

        ", $Tag_Map);

        $TagDiscussion_Map = array(
            'tagid' => 'TagID',
            'tid' => 'DiscussionID',
            'cid' => 'CategoryID',
            'timestamp' => array('Column' => 'DateInserted', 'Filter' => array($this, 'tsToDate'))
        );

        $Ex->Query("set @rownr=1000;");

        $Ex->ExportTable('TagDiscussion', "

            select tagid, cid, tid, timestamp
            from :_topic_tags__members two
            join (
                select @rownr:=@rownr+1 as tagid, members as fullname, members as slug, count
                from (
                    select members, count(*) as count
                    from :_topic_tags__members
                    group by members
                ) as tags
            ) as tagids
            on two.members = tagids.fullname
            join :_topic_tags tt
            on tt._id = _parentid
            left join :_topic t
            on substring(tt._key, 1, length(tt._key) - 5) = t._key

        ", $TagDiscussion_Map);

        //Conversations
        if (!$Ex->IndexExists('z_idx_message_key', ':_message')) {
            $Ex->Query("create index z_idx_message_key on :_message(_key);");
        }
        $Ex->Query("drop table if exists z_pmto;");
        $Ex->Query("

            create table z_pmto (
                pmid int unsigned,
                userid int,
                groupid int,
                primary key(pmid, userid)
            );

        ");

        $Ex->Query("

            insert ignore z_pmto (
                pmid,
                userid
            )
            select substring_index(_key, ':', -1), fromuid
            from :_message;

        ");

        $Ex->Query("

            insert ignore z_pmto (
                pmid,
                userid
            )
            select substring_index(_key, ':', -1), touid
            from :_message;

        ");

        $Ex->Query("drop table if exists z_pmto2;");
        $Ex->Query("

            create table z_pmto2 (
                pmid int unsigned,
                userids varchar(250),
                groupid int unsigned,
                primary key (pmid)
            );

        ");

        $Ex->Query("

            replace z_pmto2 (
                pmid,
                userids
            )
            select pmid, group_concat(userid order by userid)
            from z_pmto
            group by pmid;

        ");

        $Ex->Query("drop table if exists z_pmgroup;");
        $Ex->Query("

            create table z_pmgroup (
                userids varchar(250),
                groupid varchar(255),
                firstmessageid int,
                lastmessageid int,
                countmessages int,
                primary key (userids, groupid)
            );

        ");

        $Ex->Query("

            insert z_pmgroup
            select userids, concat('message:', min(pmid)), min(pmid), max(pmid), count(*)
            from z_pmto2
            group by userids;

        ");

        $Ex->Query("

            update z_pmto2 as p
            left join z_pmgroup g
            on p.userids = g.userids
            set p.groupid = g.firstmessageid;

        ");

        $Ex->Query("

            update z_pmto as p
            left join z_pmto2 p2
            on p.pmid = p2.pmid
            set p.groupid = p2.groupid;

        ");

        $Ex->Query("create index z_idx_pmto_cid on z_pmto(groupid);");
        $Ex->Query("create index z_idx_pmgroup_cid on z_pmgroup(firstmessageid);");

        $Conversation_Map = array(
            'conversationid' => 'ConversationID',
            'firstmessageid' => 'FirstMessageID',
            'lastmessageid' => 'LastMessageID',
            'countparticipants' => 'CountParticipants',
            'countmessages' => 'CountMessages'
        );

        $Ex->ExportTable('Conversation', "

            select *, firstmessageid as conversationid, 2 as countparticipants
            from z_pmgroup
            left join :_message
            on groupid = _key;

        ", $Conversation_Map);


        $ConversationMessage_Map = array(
            'messageid' => 'MessageID',
            'conversationid' => 'ConversationID',
            'content' => 'Body',
            'format' => 'Format',
            'fromuid' => 'InsertUserID',
            'timestamp' => array('Column' => 'DateInserted', 'Filter' => array($this, 'tsToDate'))
        );

        $Ex->ExportTable('ConversationMessage', "

            select groupid as conversationid, pmid as messageid, content, 'Text' as format, fromuid, timestamp
            from z_pmto2
            left join :_message
            on concat('message:', pmid) = _key

        ", $ConversationMessage_Map);

        $UserConversationMap = array(
            'conversationid' => 'ConversationID',
            'userid' => 'UserID',
            'lastmessageid' => 'LastMessageID'
        );

        $Ex->ExportTable('UserConversation', "

            select p.groupid as conversationid, userid, lastmessageid
            from z_pmto p
            left join z_pmgroup
            on firstmessageid = p.groupid;

        ", $UserConversationMap);

        //Bookmarks (watch)
        $UserDiscussion_Map = array(
            'members' => 'UserID',
            '_key' => array('Column' => 'DiscussionID', 'Filter' => array($this, 'idFromKey')),
            'bookmarked' => 'Bookmarked'
        );

        $Ex->ExportTable('UserDiscussion', "
            select members, _key, 1 as bookmarked
            from :_tid_followers__members
            left join :_tid_followers
            on _parentid = _id
        ", $UserDiscussion_Map);

        //Reactions
        if (!$Ex->IndexExists('z_idx_topic_mainpid', ':_topic')) {
            $Ex->Query("create index z_idx_topic_mainpid on :_topic(mainPid);");
        }
        if (!$Ex->IndexExists('z_idx_uid_downvote', ':_uid_downvote')) {
            $Ex->Query("create index z_idx_uid_downvote on :_uid_downvote(value);");
        }
        if (!$Ex->IndexExists('z_idx_uid_upvote', ':_uid_upvote')) {
            $Ex->Query("create index z_idx_uid_upvote on :_uid_upvote(value);");
        }

        $UserTag_Map = array(
            'tagid' => 'TagID',
            'recordtype' => 'RecordType',
            '_key' => array('Column' => 'UserID', 'Filter' => array($this, 'idFromKey')),
            'value' => 'RecordID',
            'score' => array('Column' => 'DateInserted', 'Filter' => array($this, 'tsToDate')),
            'total' => 'Total'
        );

        $Ex->ExportTable('UserTag', "

            select 11 as tagid, 'Discussion' as recordtype, u._key, u.value, score, total
            from :_uid_upvote u
            left join z_discussionids t
            on u.value = t.tid
            left join z_reactiontotalsupvote r
            on  r.value = u.value
            where u._key != 'uid:NaN:upvote'
            and t.tid is not null

            union

            select 11 as tagid, 'Comment' as recordtype, u._key, u.value, score, total
            from :_uid_upvote u
            left join z_discussionids t
            on u.value = t.tid
            left join z_reactiontotalsupvote r
            on  r.value = u.value
            where u._key != 'uid:NaN:upvote'
            and t.tid is null

            union

            select 10 as tagid, 'Discussion' as recordtype, u._key, u.value, score, total
            from :_uid_downvote u
            left join z_discussionids t
            on u.value = t.tid
            left join z_reactiontotalsdownvote r
            on  r.value = u.value
            where u._key != 'uid:NaN:downvote'
            and t.tid is not null

            union

            select 10 as tagid, 'Comment' as recordtype, u._key, u.value, score, total
            from :_uid_downvote u
            left join z_discussionids t
            on u.value = t.tid
            left join z_reactiontotalsdownvote r
            on  r.value = u.value
            where u._key != 'uid:NaN:downvote'
            and t.tid is null

        ", $UserTag_Map);

        //TODO: Permissions

        $Ex->EndExport();

    }

    public function nameToSlug($name) {
        return $this->Url($name);
    }

    protected $_UrlTranslations = array(
        '–' => '-',
        '—' => '-',
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'Ae',
        'Ä' => 'A',
        'Å' => 'A',
        'Ā' => 'A',
        'Ą' => 'A',
        'Ă' => 'A',
        'Æ' => 'Ae',
        'Ç' => 'C',
        'Ć' => 'C',
        'Č' => 'C',
        'Ĉ' => 'C',
        'Ċ' => 'C',
        'Ď' => 'D',
        'Đ' => 'D',
        'Ð' => 'D',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ē' => 'E',
        'Ě' => 'E',
        'Ĕ' => 'E',
        'Ė' => 'E',
        'Ĝ' => 'G',
        'Ğ' => 'G',
        'Ġ' => 'G',
        'Ģ' => 'G',
        'Ĥ' => 'H',
        'Ħ' => 'H',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ī' => 'I',
        'Ĩ' => 'I',
        'Ĭ' => 'I',
        'Į' => 'I',
        'İ' => 'I',
        'Ĳ' => 'IJ',
        'Ĵ' => 'J',
        'Ķ' => 'K',
        'Ł' => 'K',
        'Ľ' => 'K',
        'Ĺ' => 'K',
        'Ļ' => 'K',
        'Ŀ' => 'K',
        'Ñ' => 'N',
        'Ń' => 'N',
        'Ň' => 'N',
        'Ņ' => 'N',
        'Ŋ' => 'N',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'Oe',
        'Ö' => 'Oe',
        'Ō' => 'O',
        'Ő' => 'O',
        'Ŏ' => 'O',
        'Œ' => 'OE',
        'Ŕ' => 'R',
        'Ŗ' => 'R',
        'Ś' => 'S',
        'Š' => 'S',
        'Ş' => 'S',
        'Ŝ' => 'S',
        'Ť' => 'T',
        'Ţ' => 'T',
        'Ŧ' => 'T',
        'Ț' => 'T',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ü' => 'Ue',
        'Ū' => 'U',
        'Ü' => 'Ue',
        'Ů' => 'U',
        'Ű' => 'U',
        'Ŭ' => 'U',
        'Ũ' => 'U',
        'Ų' => 'U',
        'Ŵ' => 'W',
        'Ý' => 'Y',
        'Ŷ' => 'Y',
        'Ÿ' => 'Y',
        'Ź' => 'Z',
        'Ž' => 'Z',
        'Ż' => 'Z',
        'Þ' => 'T',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'ae',
        'ä' => 'ae',
        'å' => 'a',
        'ā' => 'a',
        'ą' => 'a',
        'ă' => 'a',
        'æ' => 'ae',
        'ç' => 'c',
        'ć' => 'c',
        'č' => 'c',
        'ĉ' => 'c',
        'ċ' => 'c',
        'ď' => 'd',
        'đ' => 'd',
        'ð' => 'd',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ē' => 'e',
        'ę' => 'e',
        'ě' => 'e',
        'ĕ' => 'e',
        'ė' => 'e',
        'ƒ' => 'f',
        'ĝ' => 'g',
        'ğ' => 'g',
        'ġ' => 'g',
        'ģ' => 'g',
        'ĥ' => 'h',
        'ħ' => 'h',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ī' => 'i',
        'ĩ' => 'i',
        'ĭ' => 'i',
        'į' => 'i',
        'ı' => 'i',
        'ĳ' => 'ij',
        'ĵ' => 'j',
        'ķ' => 'k',
        'ĸ' => 'k',
        'ł' => 'l',
        'ľ' => 'l',
        'ĺ' => 'l',
        'ļ' => 'l',
        'ŀ' => 'l',
        'ñ' => 'n',
        'ń' => 'n',
        'ň' => 'n',
        'ņ' => 'n',
        'ŉ' => 'n',
        'ŋ' => 'n',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'oe',
        'ö' => 'oe',
        'ø' => 'o',
        'ō' => 'o',
        'ő' => 'o',
        'ŏ' => 'o',
        'œ' => 'oe',
        'ŕ' => 'r',
        'ř' => 'r',
        'ŗ' => 'r',
        'š' => 's',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'ü' => 'ue',
        'ū' => 'u',
        'ü' => 'ue',
        'ů' => 'u',
        'ű' => 'u',
        'ŭ' => 'u',
        'ũ' => 'u',
        'ų' => 'u',
        'ŵ' => 'w',
        'ý' => 'y',
        'ÿ' => 'y',
        'ŷ' => 'y',
        'ž' => 'z',
        'ż' => 'z',
        'ź' => 'z',
        'þ' => 't',
        'ß' => 'ss',
        'ſ' => 'ss',
        'А' => 'A',
        'Б' => 'B',
        'В' => 'V',
        'Г' => 'G',
        'Д' => 'D',
        'Е' => 'E',
        'Ё' => 'YO',
        'Ж' => 'ZH',
        'З' => 'Z',
        'Й' => 'Y',
        'К' => 'K',
        'Л' => 'L',
        'М' => 'M',
        'Н' => 'N',
        'О' => 'O',
        'П' => 'P',
        'Р' => 'R',
        'С' => 'S',
        'ș' => 's',
        'ț' => 't',
        'Ț' => 'T',
        'Т' => 'T',
        'У' => 'U',
        'Ф' => 'F',
        'Х' => 'H',
        'Ц' => 'C',
        'Ч' => 'CH',
        'Ш' => 'SH',
        'Щ' => 'SCH',
        'Ъ' => '',
        'Ы' => 'Y',
        'Ь' => '',
        'Э' => 'E',
        'Ю' => 'YU',
        'Я' => 'YA',
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'yo',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'c',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'sch',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya'
    );

    public function Url($Mixed) {

        // Preliminary decoding
        $Mixed = strip_tags(html_entity_decode($Mixed, ENT_COMPAT, 'UTF-8'));
        $Mixed = strtr($Mixed, $this->_UrlTranslations);
        $Mixed = preg_replace('`[\']`', '', $Mixed);

        // Test for Unicode PCRE support
        // On non-UTF8 systems this will result in a blank string.
        $UnicodeSupport = (preg_replace('`[\pP]`u', '', 'P') != '');

        // Convert punctuation, symbols, and spaces to hyphens
        if ($UnicodeSupport) {
            $Mixed = preg_replace('`[\pP\pS\s]`u', '-', $Mixed);
        } else {
            $Mixed = preg_replace('`[\s_[^\w\d]]`', '-', $Mixed);
        }

        // Lowercase, no trailing or repeat hyphens
        $Mixed = preg_replace('`-+`', '-', strtolower($Mixed));
        $Mixed = trim($Mixed, '-');

        return rawurlencode($Mixed);
    }

    public function tsToDate($time) {
        if (!$time) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $time / 1000);
    }

    public function removeNumId($slug) {
        $regex = '/(\d*)\//';
        $newslug = preg_replace($regex, '', $slug);
    }

    public function roleNameFromKey($key) {
        $regex = '/\w*:([\w|\s|-]*):/';
        preg_match($regex, $key, $matches);

        return $matches[1];
    }

    public function idFromKey($key) {
        $regex = '/\w*:(\d*):/';
        preg_match($regex, $key, $matches);

        return $matches[1];
    }

    public function makeNullZero($value) {
        if (!$value) {
            return 0;
        }

        return $value;
    }

    public function isPoll($value) {
        if ($value) {
            return 'poll';
        }

        return null;
    }

    public function serializeReactions($reactions) {
        if ($reactions == '0:0') {
            return null;
        }
        $reactionArray = explode(':', $reactions);
        $arraynum = 1;
        if ($reactionArray[0] > 0 && $reactionArray[1] > 0) {
            $arraynum = 2;
        }
        $attributes = 'a:1:{s:5:"React";a:' . $arraynum . ':{';
        if ($reactionArray[0] > 0) {
            $attributes .= 's:2:"Up";s:' . strlen($reactionArray[0]) . ':"' . $reactionArray[0] . '";';
        }
        if ($reactionArray[1] > 0) {
            $attributes .= 's:4:"Down";s:' . strlen($reactionArray[1]) . ':"' . $reactionArray[1] . '";';
        }
        $attributes .= '}}';

        return $attributes;
    }
}
