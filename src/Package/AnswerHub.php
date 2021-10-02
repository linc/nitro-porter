<?php
/**
 * AnswerHub exporter tool.
 * Assume https://github.com/vanilla/addons/tree/master/plugins/QnA will be enabled.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Alexandre Chouinard
 */

namespace NitroPorter\Package;

use NitroPorter\ExportController;

class AnswerHub extends ExportController
{

    const SUPPORTED = [
        'name' => 'answerhub',
        'prefix' => '',
        'CommandLine' => [
            'noemaildomain' => array(
                'Domain to use when generating email addresses for users that does not have one.',
                'Field' => 'noemaildomain',
                'Sx' => '::',
                'Default' => 'answerhub.com',
            ),
        ],
        'features' => [
            'Users' => 1,
            'Passwords' => 0,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 0,
            'PrivateMessages' => 0,
            'Signatures' => 0,
            'Attachments' => 1,
            'Bookmarks' => 0,
            'Permissions' => 0,
            'Badges' => 0,
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 1,
            'Reactions' => 0,
            'Articles' => 0,
        ]
    ];

    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see   $_structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex)
    {
        // Get the characterset for the comments.
        // Usually the comments table is the best target for this.
        $characterSet = $ex->getCharacterSet('nodes');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Reiterate the platform name here to be included in the porter file header.
        $ex->beginExport('', 'AnswerHub');

        $result = $ex->query("select c_reserved as lastID from id_generators where c_identifier = 'AUTHORITABLE'", true);
        if ($row = $result->nextResultRow()) {
            $lastID = $row['lastID'];
        }
        if (!isset($lastID)) {
            die('Something went wrong :S'.PHP_EOL);
        }

        // User.
        $user_Map = array(
            'c_email' => array('Column' => 'Email', 'Filter' => array($this, 'generateEmail')),
        );
        $ex->exportTable(
            'User', "
            select
                user.c_id as UserID,
                user.c_name as Name,
                sha2(concat(user.c_name, now()), 256) as Password,
                'Reset' as HashMethod,
                user.c_creation_date as DateInserted,
                user.c_birthday as DateOfBirth,
                user.c_last_seen as DateLastActive,
                user_email.c_email,
                0 as Admin
            from :_authoritables as user
                 left join :_user_emails as user_email on user_email.c_user = user.c_id
            where user.c_type = 'user'
                and user.c_name != '\$\$ANON_USER\$\$'
        ", $user_Map
        );

        // Role.
        $role_Map = array(
        );
        $ex->exportTable(
            'Role', "
            select
                groups.c_id as RoleID,
                groups.c_name as Name,
                groups.c_description as Description
            from :_authoritables as groups
            where groups.c_type = 'group'

            union all

            select
                $lastID + 1,
                'System Administrator',
                'System users from AnswerHub'
            from dual
        ", $role_Map
        );

        // User Role.
        $userRole_Map = array(
        );
        $ex->exportTable(
            'UserRole', "
            select
                user_role.c_groups as RoleID,
                user_role.c_members as UserID
            from :_authoritable_groups as user_role
        ", $userRole_Map
        );

        // Category.
        $category_Map = array(
        );
        $ex->exportTable(
            'Category', "
            select
                containers.c_id as CategoryID,
                case
                    when parents.c_type = 'space' then containers.c_parent
                    else null
                end as ParentCategoryID,
                containers.c_name as Name
            from containers as containers
            left join containers as parents on parents.c_id = containers.c_parent
            where containers.c_type = 'space'
                and containers.c_active = 1
        ", $category_Map
        );

        // Discussion.
        $discussion_Map = array(
        );
        // The query works fine but it will probably be slow for big tables
        $ex->exportTable(
            'Discussion', "
                        select
                questions.c_id as DiscussionID,
                if(questions.c_type = 'question', 'Question', NULL) as Type,
                questions.c_primaryContainer as CategoryID,
                questions.c_author as InsertUserID,
                questions.c_creation_date as DateInserted,
                questions.c_title as Name,
                case
                	       			when nr.c_body is not null and nr.c_body <> '' then nr.c_body
                	       			when questions.c_body is not null and questions.c_body <> '' then questions.c_body
                	       			else questions.c_title
                end as Body,
                'HTML' as Format,
                if(locate('[closed]', questions.c_normalized_state) > 0, 1, 0) as Closed,
                if(questions.c_type = 'question',
                if(count(answers.c_id) > 0,
                    if (locate('[accepted]', group_concat(ifnull(answers.c_normalized_state, ''))) = 0,
                        if (locate('[rejected]', group_concat(ifnull(answers.c_normalized_state, ''))) = 0,
                            'Answered',
                            'Rejected'
                        ),
                        'Accepted'
                    ),
                    'Unanswered'
                ), null) as QnA
            from :_nodes as questions
            left join (select
                        c_node, c_body
                    from :_node_revisions nr
                    where c_id in (
                        select max(c_id) as id from :_node_revisions group by c_node)
                )  nr on nr.c_node = questions.c_id
	            left join :_nodes as answers on
	                answers.c_parent = questions.c_id
	                and answers.c_type = 'answer'
	                and answers.c_visibility = 'full'
            where questions.c_type in ('question', 'topic')
                and questions.c_visibility = 'full'
            group by questions.c_id
        ", $discussion_Map
        );

        // Comment.
        $comment_Map = array(
        );
        $ex->exportTable(
            'Comment', "
            select
                answers.c_id as CommentID,
                answers.c_parent as DiscussionID,
                answers.c_author as InsertUserID,
                if(nr.c_body is not null and nr.c_body <> '', nr.c_body, answers.c_body) as Body,
                'Html' as Format,
                answers.c_creation_date as DateInserted,
                if(locate('[accepted]', answers.c_normalized_state) = 0,
                    if(locate('[rejected]', answers.c_normalized_state) = 0,
                        null,
                        'Rejected'
                    ),
                    'Accepted'
                ) as QnA
            from :_nodes as answers
            left join (select
                c_node, c_body
            from :_node_revisions nr
            where c_id in (
                select max(c_id) as id
                from :_node_revisions
                group by c_node)
                )  nr on nr.c_id = answers.c_id
            where answers.c_type in ('answer', 'comment')
                  and answers.c_visibility = 'full'
        ", $comment_Map
        );

        // Tags
        $ex->exportTable(
            'Tags', "
            select
                c_id as TagID,
                c_plug as Name,
                c_title as FullName,
                c_creation_date as DateInserted
            from :_nodes
            where n.c_type = 'topic'
        "
        );

        $ex->exportTable(
            'TagDiscussion', "
            select
                c_topics as TagID,
                c_nodes as DiscussionID,
                -1 as CategoryID,
                now() as DateInserted
            from :_node_topics
            where c_nodes in (select c_nodes from :_nodes where c_type = 'question')
        "
        );

        // Media.
        $media_Map = array(
            'Name' => array('Column' => 'Name', 'Filter' => array($this, 'getFileName')),
            'Type' => array('Column' => 'Type', 'Filter' => array($this, 'buildMimeType')),
        );
        $ex->exportTable(
            'Media', "
            select
                  m.c_id as `MediaID`,
                  m.c_name as `Name`,
                  concat('attachments', m.c_name) as `Path`,
                  m.c_mime_type as `Type`,
                  m.c_size as `Size`,
                  m.c_user as `InsertUserID`,
                  m.c_creation_date as `DateInserted`,
                  na.c_Node as `ForeignID`,
                  if(n.c_type = 'question', 'discussion', 'comment') as `ForeignTable`
            from :_managed_files as m
            join :_node_attachments na on na.c_attachments = m.c_id
            join :_nodes n on na.c_Node = n.c_id

            union

            select
                  s.c_id as `MediaID`,
                  s.c_url as `Name`,
                  s.c_url as `Path`,
                  s.c_url as `Type`,
                  1 as `Size`,
                  s.c_addedBy as `InsertUserID`,
                  s.c_creation_date as `DateInserted`,
                  s.c_node as `ForeignID`,
                  if(n.c_type = 'question', 'discussion', 'comment') as `ForeignTable`
            from :_sources s
            join :_nodes n on s.c_node = n.c_id
        ", $media_Map
        );

        $ex->endExport();
    }

    /**
     * Generate an email for users who do not have one.
     *
     * @param  $value Value of the current row
     * @param  $field Name associated with the current field value
     * @param  $row   Full data row columns
     * @return string Email
     */
    public function generateEmail($value, $field, $row)
    {
        $email = $value;

        if (empty($email)) {
            $domain = $this->param('noemaildomain');
            $slug = preg_replace('#[^a-z0-9-_.]#i', null, $row['Name']);

            if (!strlen($slug)) {
                $slug = $row['UserID'];
            }

            $email = "$slug@$domain";
        }

        return $email;
    }

    public function getFileName($value, $field, $row)
    {
        $arr = explode('/', $value);
        return end($arr);
    }

    /**
     * Set valid MIME type for images.
     *
     * @access public
     * @see    ExportModel::_exportTable
     *
     * @param  string $value Extension.
     * @param  string $field Ignored.
     * @param  array  $row   Ignored.
     * @return string Extension or accurate MIME type.
     */
    public function buildMimeType($value, $field, $row)
    {

        if(preg_match('~.*\.(.*)~', $value, $matches) != false) {
            switch (strtolower($matches[1])) {
            case 'jpg':
            case 'jpeg':
            case 'gif':
            case 'png':
                $value = 'image/'.$matches[1];
                break;
            case 'pdf':
            case 'zip':
                $value = 'application/'.$matches[1];
                break;
            case 'doc':
                $value = 'application/msword';
                break;
            case 'xls':
                $value = 'application/vnd.ms-excel';
                break;
            case 'txt':
            case 'log':
                $value = 'text/plain';
                break;
            }
        }

        return $value;
    }
}
