<?php
/**
 * User Voice exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license Proprietary
 * @package VanillaPorter
 */

$Supported['uservoice'] = array('name' => 'User Voice', 'prefix' => 'cs_');
$Supported['uservoice']['features'] = array(
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Avatars' => 1,
    'Bookmarks' => 1,
    'Signatures' => 1,
    'Passwords' => 1,
);

class UserVoice extends ExportController {
    /**
     *
     * @param ExportModel $Ex
     */
    public function ForumExport($Ex) {

        $CharacterSet = $Ex->GetCharacterSet('Threads');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        $Ex->BeginExport('', 'User Voice');
        $Ex->SourcePrefix = 'cs_';


        // User.
        $User_Map = array(
            'LastActivity' => array('Column' => 'DateLastActive'),
            'UserName' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
            'CreateDate' => array('Column' => 'DateInserted'),
        );
        $Ex->ExportTable('User', "
         select u.*,
         concat('sha1$', m.PasswordSalt, '$', m.Password) as Password,
         'django' as HashMethod,
         if(a.Content is not null, concat('import/userpics/avatar',u.UserID,'.jpg'), NULL) as Photo
         from :_Users u
         left join aspnet_Membership m on m.UserId = u.MembershipID
         left join :_UserAvatar a on a.UserID = u.UserID", $User_Map);


        // Role.
        $Role_Map = array(
            'RoleId' => array('Column' => 'RoleID', 'Filter' => array($this, 'RoleIDConverter')),
            'RoleName' => 'Name'
        );
        $Ex->ExportTable('Role', "
         select *
         from aspnet_Roles", $Role_Map);

        // User Role.
        $UserRole_Map = array(
            'RoleId' => array('Column' => 'RoleID', 'Filter' => array($this, 'RoleIDConverter')),
        );
        $Ex->ExportTable('UserRole', "
         select u.UserID, ur.RoleId
         from aspnet_UsersInRoles ur
         left join :_Users u on ur.UserId = u.MembershipID
         ", $UserRole_Map);


        // Category.
        $Category_Map = array(
            'SectionID' => 'CategoryID',
            'ParentID' => 'ParentCategoryID',
            'SortOrder' => 'Sort',
            'DateCreated' => 'DateInserted'
        );
        $Ex->ExportTable('Category', "
         select s.*
         from :_Sections s", $Category_Map);


        // Discussion.
        $Discussion_Map = array(
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
        $Ex->ExportTable('Discussion', "
         select t.*,
            p.Subject,
            p.Body,
            'Html' as Format,
            p.IPAddress as InsertIPAddress,
            if(t.IsSticky  > 0, 2, 0) as Announce
         from :_Threads t
         left join :_Posts p on p.ThreadID = t.ThreadID
         where p.SortOrder = 1", $Discussion_Map);


        // Comment.
        $Comment_Map = array(
            'PostID' => 'CommentID',
            'ThreadID' => 'DiscussionID',
            'UserID' => 'InsertUserID',
            'IPAddress' => 'InsertIPAddress',
            'Body' => array('Column' => 'Body', 'Filter' => 'HTMLDecoder'),
            'PostDate' => 'DateInserted'
        );
        $Ex->ExportTable('Comment', "
         select p.*
         from :_Posts p
         where SortOrder > 1", $Comment_Map);


        // Bookmarks
        $UserDiscussion_Map = array(
            'ThreadID' => 'DiscussionID'
        );
        $Ex->ExportTable('UserDiscussion', "
         select t.*,
            '1' as Bookmarked,
            NOW() as DateLastViewed
         from :_TrackedThreads t", $UserDiscussion_Map);

        // Media.
        /*$Media_Map = array(
           'FileName' => 'Name',
           'ContentType' => 'Type',
           'ContentSize' => 'Size',
           'UserID' => 'InsertUserID',
           'Created' => 'DateInserted'
        );
        $Ex->ExportTable('Media', "
           select a.*,
              if(p.SortOrder = 1, 'Discussion', 'Comment') as ForeignTable,
              if(p.SortOrder = 1, p.ThreadID, a.PostID) as ForeignID,
              concat('import/attach/', a.FileName) as Path
           from :_PostAttachments a
           left join :_Posts p on p.PostID = a.PostID
           where IsRemote = 0", $Media_Map);
        */

        // Decode files in database.
        $this->ExportHexAvatars();
        //$this->ExportHexAttachments();


        // El fin.
        $Ex->EndExport();
    }

    /**
     * Role IDs are crazy hex strings of hyphenated octets.
     * Create an integer RoleID using the first 4 characters.
     *
     * @param string $RoleID
     * @return int
     */
    public function RoleIDConverter($RoleID) {
        return hexdec(substr($RoleID, 0, 4));
    }

    /**
     * Avatars are hex-encoded in the database.
     */
    public function ExportHexAvatars($Thumbnail = true) {
        $this->Ex->Comment("Exporting hex encoded columns...");

        $Result = $this->Ex->Query("select UserID, Length, ContentType, Content from :_UserAvatar");
        $Path = '/www/porter/userpics';
        $Count = 0;

        while ($Row = mysql_fetch_assoc($Result)) {
            // Build path
            if (!file_exists(dirname($Path))) {
                $R = mkdir(dirname($Path), 0777, true);
                if (!$R) {
                    die("Could not create " . dirname($Path));
                }
            }

            $PhotoPath = $Path . '/pavatar' . $Row['UserID'] . '.jpg';
            file_put_contents($PhotoPath, hex2bin($Row['Content']));
            $this->Ex->Status('.');

            if ($Thumbnail) {
                if ($Thumbnail === true) {
                    $Thumbnail = 50;
                }

                //$PicPath = str_replace('/avat', '/pavat', $PhotoPath);
                $ThumbPath = str_replace('/pavat', '/navat', $PhotoPath);
                GenerateThumbnail($PhotoPath, $ThumbPath, $Thumbnail, $Thumbnail);
            }
            $Count++;
        }
        $this->Ex->Status("$Count Hex Encoded.\n");
        $this->Ex->Comment("$Count Hex Encoded.", false);
    }

    /**
     *
     */
    public function ExportHexAttachments() {
        $this->Ex->Comment("Exporting hex encoded columns...");

        $Result = $this->Ex->Query("select a.*, p.PostID
         from :_PostAttachments a
         left join :_Posts p on p.PostID = a.PostID
         where IsRemote = 0");
        $Path = '/www/porter/attach';
        $Count = 0;

        while ($Row = mysql_fetch_assoc($Result)) {
            // Build path
            if (!file_exists(dirname($Path))) {
                $R = mkdir(dirname($Path), 0777, true);
                if (!$R) {
                    die("Could not create " . dirname($Path));
                }
            }

            file_put_contents($Path . '/' . $Row['FileName'], hex2bin($Row['Content']));
            $Count++;
        }
        $this->Ex->Status("$Count Hex Encoded.\n");
        $this->Ex->Comment("$Count Hex Encoded.", false);
    }
}

/**
 * Get the file extension from a mime-type.
 * @param string $mime
 * @param string $ext If this argument is specified then this extension will be added to the list of known types.
 * @return string The file extension without the dot.
 */
function MimeToExt($mime, $ext = null) {
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
    function hex2bin($hexstr) {
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
