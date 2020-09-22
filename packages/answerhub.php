<?php
/**
 * AnswerHub exporter tool.
 * Assume https://github.com/vanilla/addons/tree/master/plugins/QnA will be enabled.
 *
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$supported['answerhub'] = array('name' => 'answerhub', 'prefix' => '');
$supported['answerhub']['CommandLine'] = array(
    'noemaildomain' => array(
        'Domain to use when generating email addresses for users that does not have one.',
        'Field' => 'noemaildomain',
        'Sx' => '::',
        'Default' => 'answerhub.com',
    ),
);
$supported['answerhub']['features'] = array(

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

class AnswerHub extends ExportController {
    /**
     * Main export process.
     *
     * @param ExportModel $ex
     * @see $_structures in ExportModel for allowed destination tables & columns.
     */
    public function forumExport($ex) {
        // Get the characterset for the comments.
        // Usually the comments table is the best target for this.
        $characterSet = $ex->getCharacterSet('network6_nodes');
        if ($characterSet) {
            $ex->characterSet = $characterSet;
        }

        // Reiterate the platform name here to be included in the porter file header.
        $ex->beginExport('', 'AnswerHub');

        $result = $ex->query("select c_reserved as lastID from :_id_generators where c_identifier = 'AUTHORITABLE'", true);
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
        $ex->exportTable('User', "
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
            from :_network6_authoritables as user
                 left join :_network6_user_emails as user_email on user_email.c_user = user.c_id
            where user.c_type = 'user'
                and user.c_name != '\$\$ANON_USER\$\$'

            union all

            select
                su.c_id + $lastID,
                su.c_username,
                sha2(concat(su.c_username, now()), 256),
                'Reset',
                su.c_creation_date,
                null,
                null,
                su.c_email,
                1 as Admin
            from :_system_users as su
            where su.c_active = 1

        ", $user_Map);

        // Role.
        $role_Map = array(
        );
        $ex->exportTable('Role', "
            select
                groups.c_id as RoleID,
                groups.c_name as Name,
                groups.c_description as Description
            from :_network6_authoritables as groups
            where groups.c_type = 'group'

            union all

            select
                $lastID + 1,
                'System Administrator',
                'System users from AnswerHub'
            from dual
        ", $role_Map);

        // User Role.
        $userRole_Map = array(
        );
        $ex->exportTable('UserRole', "
            select
                user_role.c_groups as RoleID,
                user_role.c_members as UserID
            from :_network6_authoritable_groups as user_role

            union all

            select
                $lastID + 1,
                su.c_id + $lastID
            from :_system_users as su
            where su.c_active = 1
        ", $userRole_Map);

        // Category.
        $category_Map = array(
        );
        $ex->exportTable('Category', "
            select
                containers.c_id as CategoryID,
                case
                    when parents.c_type = 'space' then containers.c_parent
                    else null
                end as ParentCategoryID,
                containers.c_name as Name
            from :_containers as containers
            left join :_containers as parents on parents.c_id = containers.c_parent
            where containers.c_type = 'space'
                and containers.c_active = 1
        ", $category_Map);

        // Discussion.
        $discussion_Map = array(
        );
        // The query works fine but it will probably be slow for big tables
        $ex->exportTable('Discussion', "
            select
                questions.c_id as DiscussionID,
                'Question' as Type,
                questions.c_primaryContainer as CategoryID,
                questions.c_author as InsertUserID,
                questions.c_creation_date as DateInserted,
                questions.c_title as Name,
                coalesce(nullif(questions.c_body, ''), questions.c_title) as Body,
                'HTML' as Format,
                if(locate('[closed]', questions.c_normalized_state) > 0, 1, 0) as Closed,
                if(count(answers.c_id) > 0,
                    if (locate('[accepted]', group_concat(ifnull(answers.c_normalized_state, ''))) = 0,
                        if (locate('[rejected]', group_concat(ifnull(answers.c_normalized_state, ''))) = 0,
                            'Answered',
                            'Rejected'
                        ),
                        'Accepted'
                    ),
                    'Unanswered'
                ) as QnA
            from :_network6_nodes as questions
	            left join :_network6_nodes as answers on
	                answers.c_parent = questions.c_id
	                and answers.c_type = 'answer'
	                and answers.c_visibility = 'full'
            where questions.c_type = 'question'
                and questions.c_visibility = 'full'
            group by questions.c_id
        ", $discussion_Map);

        // Comment.
        $comment_Map = array(
        );
        $ex->exportTable('Comment', "
            select
                answers.c_id as CommentID,
                answers.c_parent as DiscussionID,
                answers.c_author as InsertUserID,
                answers.c_body as Body,
                'Html' as Format,
                answers.c_creation_date as DateInserted,
                if(locate('[accepted]', answers.c_normalized_state) = 0,
                    if(locate('[rejected]', answers.c_normalized_state) = 0,
                        null,
                        'Rejected'
                    ),
                    'Accepted'
                ) as QnA
            from :_network6_nodes as answers
            where answers.c_type = 'answer'
                  and answers.c_visibility = 'full'
        ", $comment_Map);

        $ex->endExport();
    }

    /**
     * Generate an email for users who do not have one.
     *
     * @param $value Value of the current row
     * @param $field Name associated with the current field value
     * @param $row   Full data row columns
     * @return string Email
     */
    public function generateEmail($value, $field, $row) {
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
}

// Closing PHP tag required. (make.php)
?>
