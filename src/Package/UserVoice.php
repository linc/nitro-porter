<?php

/**
 * User Voice exporter tool
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @author  Lincoln Russell, lincolnwebs.com
 */

namespace Porter\Package;

use Porter\Package;
use Porter\ExportModel;

class UserVoice extends Package
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
            'Permissions' => 0,
            'Badges' => 0,
            'UserNotes' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Tags' => 0,
            'Reactions' => 0,
            'Articles' => 0,
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

            if ($thumbnail) {
                if ($thumbnail === true) {
                    $thumbnail = 50;
                }

                //$PicPath = str_replace('/avat', '/pavat', $photoPath);
                $thumbPath = str_replace('/pavat', '/navat', $photoPath);
                $ex->generateThumbnail($photoPath, $thumbPath, $thumbnail, $thumbnail);
            }
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
        $ex->exportTable(
            'User',
            "
         select u.*,
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
        $ex->exportTable(
            'Role',
            "
         select *
         from aspnet_Roles",
            $role_Map
        );

        // User Role.
        $userRole_Map = array(
            'RoleId' => array('Column' => 'RoleID', 'Filter' => array($this, 'roleIDConverter')),
        );
        $ex->exportTable(
            'UserRole',
            "
         select u.UserID, ur.RoleId
         from aspnet_UsersInRoles ur
         left join :_Users u on ur.UserId = u.MembershipID
         ",
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
        $ex->exportTable(
            'Category',
            "
         select s.*
         from :_Sections s",
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
        $ex->exportTable(
            'Discussion',
            "
         select t.*,
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
        $ex->exportTable(
            'Comment',
            "
         select p.*
         from :_Posts p
         where SortOrder > 1",
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
        $ex->exportTable(
            'UserDiscussion',
            "
         select t.*,
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

/**
 * Get the file extension from a mime-type.
 *
 * @param  string $mime
 * @param  string $ext  If this argument is specified then this extension will be added to the list of known types.
 * @return string The file extension without the dot.
 */
function mimeToExt($mime, $ext = null)
{
    static $known = array('text/plain' => 'txt', 'image/jpeg' => 'jpg');
    $mime = strtolower($mime);

    if ($ext !== null) {
        $known[$mime] = ltrim($ext, '.');
    }

    if (array_key_exists($mime, $known)) {
        return $known[$mime];
    }

    // We don't know the mime type so we need to just return the second part as the extension.
    $result = trim(strrchr($mime, '/'), '/');

    if (substr($result, 0, 2) === 'x-') {
        $result = substr($result, 2);
    }

    return $result;
}

if (!function_exists('hex2bin')) {
    function hex2bin($hexstr)
    {
        $n = strlen($hexstr);
        $sbin = "";
        $i = 0;
        while ($i < $n) {
            $a = substr($hexstr, $i, 2);
            $c = pack("H*", $a);
            if ($i == 0) {
                $sbin = $c;
            } else {
                $sbin .= $c;
            }
            $i += 2;
        }

        return $sbin;
    }
}
