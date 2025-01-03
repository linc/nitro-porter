<?php

/**
 * User Voice exporter tool
 *
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Source;

use Porter\Source;
use Porter\ExportModel;

class UserVoice extends Source
{
    public const SUPPORTED = [
        'name' => 'User Voice',
        'prefix' => 'cs_',
        'charset_table' => 'Threads',
        'features' => [
            'Users' => 1,
            'Passwords' => 1,
            'Categories' => 1,
            'Discussions' => 1,
            'Comments' => 1,
            'Polls' => 0,
            'Roles' => 1,
            'Avatars' => 1,
            'PrivateMessages' => 0,
            'Signatures' => 1,
            'Attachments' => 0,
            'Bookmarks' => 1,
        ]
    ];

    /**
     * Main export method.
     *
     * @param ExportModel $ex
     */
    public function run($ex)
    {
        $this->users($ex);
        $this->roles($ex);

        $this->categories($ex);
        $this->discussions($ex);
        $this->comments($ex);
        $this->bookmarks($ex);

        //$this->attachments();
        // Decode files in database.
        $this->exportHexAvatars($ex);
        //$this->ExportHexAttachments($ex);
    }

    /**
     * Role IDs are crazy hex strings of hyphenated octets.
     * Create an integer RoleID using the first 4 characters.
     *
     * @param  string $roleID
     * @return int
     */
    public function roleIDConverter($roleID)
    {
        return hexdec(substr($roleID, 0, 4));
    }

    /**
     * Avatars are hex-encoded in the database.
     */
    public function exportHexAvatars($ex)
    {
        $thumbnail = true;
        $ex->comment("Exporting hex encoded columns...");

        $result = $ex->query("select UserID, Length, ContentType, Content from :_UserAvatar");
        $path = '/www/porter/userpics';
        $count = 0;

        while ($row = $result->nextResultRow()) {
            // Build path
            if (!file_exists(dirname($path))) {
                $r = mkdir(dirname($path), 0777, true);
                if (!$r) {
                    die("Could not create " . dirname($path));
                }
            }

            $photoPath = $path . '/pavatar' . $row['UserID'] . '.jpg';
            file_put_contents($photoPath, hex2bin($row['Content']));
            $ex->status('.');

            if ($thumbnail === true) {
                $thumbnail = 50;
            }

            //$PicPath = str_replace('/avat', '/pavat', $photoPath);
            $thumbPath = str_replace('/pavat', '/navat', $photoPath);
            generateThumbnail($photoPath, $thumbPath, $thumbnail, $thumbnail);
            $count++;
        }
        $ex->status("$count Hex Encoded.\n");
        $ex->comment("$count Hex Encoded.", false);
    }

    /**
     *
     */
    public function exportHexAttachments($ex)
    {
        $ex->comment("Exporting hex encoded columns...");

        $result = $ex->query(
            "select a.*, p.PostID
                from :_PostAttachments a
                left join :_Posts p on p.PostID = a.PostID
                where IsRemote = 0"
        );
        $path = '/www/porter/attach';
        $count = 0;

        while ($row = $result->nextResultRow()) {
            // Build path
            if (!file_exists(dirname($path))) {
                $r = mkdir(dirname($path), 0777, true);
                if (!$r) {
                    die("Could not create " . dirname($path));
                }
            }

            file_put_contents($path . '/' . $row['FileName'], hex2bin($row['Content']));
            $count++;
        }
        $ex->status("$count Hex Encoded.\n");
        $ex->comment("$count Hex Encoded.", false);
    }

    /**
     * @param ExportModel $ex
     */
    protected function users(ExportModel $ex): void
    {
        $user_Map = array(
            'LastActivity' => array('Column' => 'DateLastActive'),
            'UserName' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'CreateDate' => array('Column' => 'DateInserted'),
        );
        $ex->export(
            'User',
            "select u.*,
                    concat('sha1$', m.PasswordSalt, '$', m.Password) as Password,
                    'django' as HashMethod,
                    if(a.Content is not null, concat('import/userpics/avatar',u.UserID,'.jpg'), NULL) as Photo
                from :_Users u
                left join aspnet_Membership m on m.UserId = u.MembershipID
                left join :_UserAvatar a on a.UserID = u.UserID",
            $user_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function roles(ExportModel $ex): void
    {
        $role_Map = array(
            'RoleId' => array('Column' => 'RoleID', 'Filter' => array($this, 'roleIDConverter')),
            'RoleName' => 'Name'
        );
        $ex->export(
            'Role',
            "select * from aspnet_Roles",
            $role_Map
        );

        // User Role.
        $userRole_Map = array(
            'RoleId' => array('Column' => 'RoleID', 'Filter' => array($this, 'roleIDConverter')),
        );
        $ex->export(
            'UserRole',
            "select u.UserID, ur.RoleId
                from aspnet_UsersInRoles ur
                left join :_Users u on ur.UserId = u.MembershipID",
            $userRole_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function categories(ExportModel $ex): void
    {
        $category_Map = array(
            'SectionID' => 'CategoryID',
            'ParentID' => 'ParentCategoryID',
            'SortOrder' => 'Sort',
            'DateCreated' => 'DateInserted'
        );
        $ex->export(
            'Category',
            "select s.* from :_Sections s",
            $category_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function discussions(ExportModel $ex): void
    {
        $discussion_Map = array(
            'ThreadID' => 'DiscussionID',
            'SectionID' => 'CategoryID',
            'UserID' => 'InsertUserID',
            'PostDate' => 'DateInserted',
            'ThreadDate' => 'DateLastComment',
            'TotalViews' => 'CountViews',
            'TotalReplies' => 'CountComments',
            'IsLocked' => 'Closed',
            'MostRecentPostAuthorID' => 'LastCommentUserID',
            'MostRecentPostID' => 'LastCommentID',
            'Subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'Body' => array('Column' => 'Body', 'Filter' => 'HTMLDecoder'),
            'IPAddress' => 'InsertIPAddress'
        );
        $ex->export(
            'Discussion',
            "select t.*,
                    p.Subject,
                    p.Body,
                    'Html' as Format,
                    p.IPAddress as InsertIPAddress,
                    if(t.IsSticky  > 0, 2, 0) as Announce
                from :_Threads t
                left join :_Posts p on p.ThreadID = t.ThreadID
                where p.SortOrder = 1",
            $discussion_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function comments(ExportModel $ex): void
    {
        $comment_Map = array(
            'PostID' => 'CommentID',
            'ThreadID' => 'DiscussionID',
            'UserID' => 'InsertUserID',
            'IPAddress' => 'InsertIPAddress',
            'Body' => array('Column' => 'Body', 'Filter' => 'HTMLDecoder'),
            'PostDate' => 'DateInserted'
        );
        $ex->export(
            'Comment',
            "select p.* from :_Posts p where SortOrder > 1",
            $comment_Map
        );
    }

    /**
     * @param ExportModel $ex
     */
    protected function bookmarks(ExportModel $ex): void
    {
        $userDiscussion_Map = array(
            'ThreadID' => 'DiscussionID'
        );
        $ex->export(
            'UserDiscussion',
            "select t.*,
                    '1' as Bookmarked,
                    NOW() as DateLastViewed
                from :_TrackedThreads t",
            $userDiscussion_Map
        );
    }

    /*protected function attachments(): void
    {
        $Media_Map = array(
           'FileName' => 'Name',
           'ContentType' => 'Type',
           'ContentSize' => 'Size',
           'UserID' => 'InsertUserID',
           'Created' => 'DateInserted'
        );
        $ex->ExportTable('Media', "
           select a.*,
              if(p.SortOrder = 1, 'Discussion', 'Comment') as ForeignTable,
              if(p.SortOrder = 1, p.ThreadID, a.PostID) as ForeignID,
              concat('import/attach/', a.FileName) as Path
           from :_PostAttachments a
           left join :_Posts p on p.PostID = a.PostID
           where IsRemote = 0", $Media_Map);
    }*/
}
