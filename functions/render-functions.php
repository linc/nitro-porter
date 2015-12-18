<?php
/**
 * Views for Vanilla 2 export tools.
 *
 * @copyright Vanilla Forums Inc. 2010-2015
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * HTML header.
 */
function PageHeader() {
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vanilla Porter - Forum Export Tool</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" type="text/css" href="style.css" media="screen"/>
    <script src="jquery.min.js"></script>
</head>
<body>
<div id="Frame">
    <div id="Content">
        <div class="Title">
            <h1>
                <img src="./vanilla_logo.png" alt="Vanilla">

                <p>Vanilla Porter <span class="Version">Version <?php echo APPLICATION_VERSION; ?></span></p>
            </h1>
        </div>
        <?php
        }

        /**
         * HTML footer.
         */
        function PageFooter() {
        ?>
    </div>
</div>
</body>
</html><?php

}

/**
 * Message: Write permission fail.
 */
function ViewNoPermission($msg) {
    PageHeader(); ?>
    <div class="Messages Errors">
        <ul>
            <li><?php echo $msg; ?></li>
        </ul>
    </div>

    <?php PageFooter();
}

/**
 * Form: Database connection info.
 */
function ViewForm($Data) {
    $forums = GetValue('Supported', $Data, array());
    $msg = GetValue('Msg', $Data, '');
    $CanWrite = GetValue('CanWrite', $Data, null);

    if ($CanWrite === null) {
        $CanWrite = TestWrite();
    }
    if (!$CanWrite) {
        $msg = 'The porter does not have write permission to write to this folder. You need to give the porter permission to create files so that it can generate the export file.' . $msg;
    }

    if (defined('CONSOLE')) {
        echo $msg . "\n";

        return;
    }

    PageHeader(); ?>
    <div class="Info">
        Howdy, stranger! Glad to see you headed our way.
        For help,
        <a href="http://docs.vanillaforums.com/developers/importing/porter" style="text-decoration:underline;"
           target="_blank">peek at the docs</a>.
        To see what data we can grab from your platform,
        <a href="?features=1" style="text-decoration:underline;">see this table</a>.
    </div>
    <form action="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET); ?>" method="post">
        <input type="hidden" name="step" value="info"/>

        <div class="Form">
            <?php if ($msg != '') : ?>
                <div class="Messages Errors">
                    <ul>
                        <li><?php echo $msg; ?></li>
                    </ul>
                </div>
            <?php endif; ?>
            <ul>
                <li>
                    <label>
                        Source Forum Type
                        <select name="type" id="ForumType">
                            <?php foreach ($forums as $forumClass => $forumInfo) : ?>
                                <option value="<?php echo $forumClass; ?>"<?php
                                if (GetValue('type') == $forumClass) {
                                    echo ' selected="selected"';
                                } ?>><?php echo $forumInfo['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </li>
                <li>
                    <label>Table Prefix <span>Most installations have a database prefix. If you&rsquo;re sure you don&rsquo;t have one, leave this blank.</span>
                        <input class="InputBox" type="text" name="prefix"
                            value="<?php echo htmlspecialchars(GetValue('prefix')) != '' ? htmlspecialchars(GetValue('prefix')) : $forums['vanilla1']['prefix']; ?>"
                            id="ForumPrefix"/>
                    </label>
                </li>
                <li>
                    <label>
                        Database Host <span>Usually "localhost".</span>
                        <input class="InputBox" type="text" name="dbhost"
                            value="<?php echo htmlspecialchars(GetValue('dbhost', '', 'localhost')) ?>"/>
                    </label>
                </li>
                <li>
                    <label>
                        Database Name
                        <input class="InputBox" type="text" name="dbname"
                            value="<?php echo htmlspecialchars(GetValue('dbname')) ?>"/>
                    </label>
                </li>
                <li>
                    <label>
                        Database Username
                        <input class="InputBox" type="text" name="dbuser"
                            value="<?php echo htmlspecialchars(GetValue('dbuser')) ?>"/>
                    </label>
                </li>
                <li>
                    <label>Database Password
                        <input class="InputBox" type="password" name="dbpass" value="<?php echo GetValue('dbpass') ?>"/>
                    </label>
                </li>
                <li>
                    <label>
                        Export Type
                        <select name="tables" id="ExportTables">
                            <option value="">All supported data</option>
                            <option value="User,Role,UserRole,Permission">Only users and roles</option>
                        </select>
                    </label>
                </li>
                <li id="FileExports">
                    <fieldset>
                        <legend>Export Options:</legend>
                        <label>
                            Avatars
                            <input type="checkbox" name="avatars" value="1">
                        </label>
                        <label>
                            Files
                            <input type="checkbox" name="files" value="1">
                        </label>

                    </fieldset>
                </li>
            </ul>
            <div class="Button">
                <input class="Button" type="submit" value="Begin Export"/>
            </div>
        </div>
    </form>
    <script type="text/javascript">
        $('#ForumType')
            .change(function() {
                var type = $(this).val();
                switch (type) {
                    <?php
                    foreach($forums as $ForumClass => $ForumInfo) {
                        $exportOptions = "\$('#FileExports > fieldset, #FileExports input').prop('disabled', true);";

                        $hasAvatars = !empty($ForumInfo['features']['Avatars']);
                        $hasAttachments = !empty($ForumInfo['features']['Attachments']);

                        if ($hasAvatars || $hasAttachments) {
                            $exportOptions = "\$('#FileExports > fieldset').prop('disabled', false);";
                            $exportOptions .= "\$('#FileExports input[name=avatars]').prop('disabled', ".($hasAvatars ? 'false' : 'true').")";
                            if ($hasAvatars) {
                                $exportOptions .= ".parent().removeClass('disabled');";
                            } else {
                                $exportOptions .= ".parent().addClass('disabled');";
                            }
                            $exportOptions .= "\$('#FileExports input[name=files]').prop('disabled', ".($hasAttachments ? 'false' : 'true').")";
                            if ($hasAttachments) {
                                $exportOptions .= ".parent().removeClass('disabled');";
                            } else {
                                $exportOptions .= ".parent().addClass('disabled');";
                            }
                        }
                    ?>
                    case '<?= $ForumClass; ?>':
                    <?= $exportOptions; ?>
                        $('#ForumPrefix').val('<?= $ForumInfo['prefix']; ?>');
                        break;
                    <?php } ?>
                }
            })
            .trigger('change');
    </script>

    <?php PageFooter();
}

/**
 * Message: Result of export.
 *
 * @param array $Msgs Comments / logs from the export.
 * @param string $Class CSS class for wrapper.
 * @param string|bool $Path Path to file for download, or false.
 */
function ViewExportResult($Msgs = array(), $Class = 'Info', $Path = false) {
    if (defined('CONSOLE')) {
        return;
    }

    PageHeader();

    echo "<p class=\"DownloadLink\">Success!";
    if ($Path) {
        " <a href=\"$Path\"><b>Download exported file</b></a>";
    }
    echo "</p>";

    if (count($Msgs)) {
        echo "<div class=\"$Class\">";
        echo "<p>Really boring export logs follow:</p>\n";
        foreach ($Msgs as $Msg) {
            echo "<p>$Msg</p>\n";
        }

        echo "<p>It worked! You&rsquo;re free! Sweet, sweet victory.</p>\n";
        echo "</div>";
    }
    PageFooter();
}

/**
 * Output a definition list of features for a single platform.
 *
 * @param string $Platform
 * @param array $Features
 */
function ViewFeatureList($Platform, $Features = array()) {
    global $Supported;

    PageHeader();

    echo '<div class="Info">';
    echo '<h2>' . $Supported[$Platform]['name'] . '</h2>';
    echo '<dl>';

    foreach ($Features as $Feature => $Trash) {
        echo '
      <dt>' . FeatureName($Feature) . '</dt>
      <dd>' . FeatureStatus($Platform, $Feature) . '</dd>';
    }
    echo '</dl>';

    PageFooter();
}

/**
 * Output a table of features per all platforms.
 *
 * @param array $Features
 */
function ViewFeatureTable($Features = array()) {
    global $Supported;
    $Platforms = array_keys($Supported);

    PageHeader();
    echo '<h2 class="FeatureTitle">Data currently supported per platform</h2>';
    echo '<p>Click any platform name for details, or <a href="/" style="text-decoration:underline;">go back</a>.</p>';
    echo '<table class="Features"><thead><tr>';

    // Header row of labels for each platform
    echo '<th><i>Feature</i></th>';
    foreach ($Platforms as $Slug) {
        echo '<th class="Platform"><div><span><a href="?features=1&type=' . $Slug . '">' . $Supported[$Slug]['name'] . '</a></span></div></th>';
    }

    echo '</tr></thead><tbody>';

    // Checklist of features per platform.
    foreach ($Features as $Feature => $Trash) {
        // Name
        echo '<tr><td class="FeatureName">' . FeatureName($Feature) . '</td>';

        // Status per platform.
        foreach ($Platforms as $Platform) {
            echo '<td>' . FeatureStatus($Platform, $Feature, false) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table>';
    PageFooter();
}

?>
