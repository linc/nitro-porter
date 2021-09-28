<?php
/**
 * Views for Vanilla 2 export tools.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

/**
 * HTML header.
 */
function pageHeader() {
$vanillaLogoBase64Encoded = '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';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Nitro Porter - Forum Export Tool</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" type="text/css" href="style.css" media="screen"/>
    <script src="jquery.min.js"></script>
</head>
<body>
<div id="Frame">
    <div id="Content">
        <div class="Title">
            <h1>
                <p>Nitro Porter <span class="Version">Version <?php echo APPLICATION_VERSION; ?></span></p>
            </h1>
        </div>
        <?php
        }

        /**
         * HTML footer.
         */
        function pageFooter() {
        ?>
    </div>
</div>
</body>
</html><?php

}

/**
 * Message: Write permission fail.
 */
function viewNoPermission($msg) {
    pageHeader(); ?>
    <div class="Messages Errors">
        <ul>
            <li><?php echo $msg; ?></li>
        </ul>
    </div>

    <?php pageFooter();
}

/**
 * Form: Database connection info.
 */
function viewForm($data) {
    $forums = getValue('Supported', $data, array());
    $msg = getValue('Msg', $data, '');
    $canWrite = getValue('CanWrite', $data, null);

    if ($canWrite === null) {
        $canWrite = testWrite();
    }
    if (!$canWrite) {
        $msg = 'The porter does not have write permission to write to this folder. You need to give the porter permission to create files so that it can generate the export file.' . $msg;
    }

    if (defined('CONSOLE')) {
        echo $msg . "\n";

        return;
    }

    pageHeader(); ?>
    <div class="Info">
        Need help?
        <a href="https://success.vanillaforums.com/kb/articles/150-vanilla-porter-guide" style="text-decoration:underline;"
           target="_blank">Try the guide</a> and peep our
        <a href="?features=1" style="text-decoration:underline;">feature support table</a>.
    </div>
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?' . http_build_query($_GET); ?>" method="post">
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
                                if (getValue('type') == $forumClass) {
                                    echo ' selected="selected"';
                                } ?>><?php echo $forumInfo['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </li>
                <li>
                    <label>Table Prefix <span>Most installations have a database prefix. If you&rsquo;re sure you don&rsquo;t have one, leave this blank.</span>
                        <input class="InputBox" type="text" name="prefix"
                            value="<?php echo htmlspecialchars(getValue('prefix')) != '' ? htmlspecialchars(getValue('prefix')) : $forums['vanilla1']['prefix']; ?>"
                            id="ForumPrefix"/>
                    </label>
                </li>
                <li>
                    <label>
                        Database Host <span>Usually "localhost".</span>
                        <input class="InputBox" type="text" name="dbhost"
                            value="<?php echo htmlspecialchars(getValue('dbhost', '', 'localhost')) ?>"/>
                    </label>
                </li>
                <li>
                    <label>
                        Database Name
                        <input class="InputBox" type="text" name="dbname"
                            value="<?php echo htmlspecialchars(getValue('dbname')) ?>"/>
                    </label>
                </li>
                <li>
                    <label>
                        Database Username
                        <input class="InputBox" type="text" name="dbuser"
                            value="<?php echo htmlspecialchars(getValue('dbuser')) ?>"/>
                    </label>
                </li>
                <li>
                    <label>Database Password
                        <input class="InputBox" type="password" name="dbpass" value="<?php echo htmlspecialchars(getValue('dbpass')) ?>"/>
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
                    foreach($forums as $forumClass => $forumInfo) {
                        $exportOptions = "\$('#FileExports > fieldset, #FileExports input').prop('disabled', true);";

                        $hasAvatars = !empty($forumInfo['features']['Avatars']);
                        $hasAttachments = !empty($forumInfo['features']['Attachments']);

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
                    case '<?= $forumClass; ?>':
                    <?= $exportOptions; ?>
                        $('#ForumPrefix').val('<?= $forumInfo['prefix']; ?>');
                        break;
                    <?php } ?>
                }
            })
            .trigger('change');
    </script>

    <?php pageFooter();
}

/**
 * Message: Result of export.
 *
 * @param array $msgs Comments / logs from the export.
 * @param string $class CSS class for wrapper.
 * @param string|bool $path Path to file for download, or false.
 */
function viewExportResult($msgs = array(), $class = 'Info', $path = false) {
    if (defined('CONSOLE')) {
        return;
    }

    pageHeader();

    echo "<p class=\"DownloadLink\">Success!";
    if ($path) {
        " <a href=\"$path\"><b>Download exported file</b></a>";
    }
    echo "</p>";

    if (count($msgs)) {
        echo "<div class=\"$class\">";
        echo "<p>Really boring export logs follow:</p>\n";
        foreach ($msgs as $msg) {
            echo "<p>$msg</p>\n";
        }

        echo "<p>It worked! You&rsquo;re free! Sweet, sweet victory.</p>\n";
        echo "</div>";
    }
    pageFooter();
}

/**
 * Output a definition list of features for a single platform.
 *
 * @param string $platform
 * @param array $features
 */
function viewFeatureList($platform, $features = array()) {
    global $supported;

    pageHeader();

    echo '<div class="Info">';
    echo '<h2>' . htmlspecialchars($supported[$platform]['name']) . '</h2>';
    echo '<dl>';

    foreach ($features as $feature => $trash) {
        echo '
      <dt>' . featureName($feature) . '</dt>
      <dd>' . featureStatus($platform, $feature) . '</dd>';
    }
    echo '</dl>';

    pageFooter();
}

/**
 * Output a table of features per all platforms.
 *
 * @param array $features
 */
function viewFeatureTable($features = array()) {
    global $supported;
    $platforms = array_keys($supported);

    pageHeader();
    echo '<h2 class="FeatureTitle">Data currently supported per platform</h2>';
    echo '<p>Click any platform name for details, or <a href="/" style="text-decoration:underline;">go back</a>.</p>';
    echo '<table class="Features"><thead><tr>';

    // Header row of labels for each platform
    echo '<th><i>Feature</i></th>';
    foreach ($platforms as $slug) {
        echo '<th class="Platform"><div><span><a href="?features=1&type=' . $slug . '">' . $supported[$slug]['name'] . '</a></span></div></th>';
    }

    echo '</tr></thead><tbody>';

    // Checklist of features per platform.
    foreach ($features as $feature => $trash) {
        // Name
        echo '<tr><td class="FeatureName">' . featureName($feature) . '</td>';

        // Status per platform.
        foreach ($platforms as $platform) {
            echo '<td>' . featureStatus($platform, $feature, false) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table>';
    pageFooter();
}

?>
