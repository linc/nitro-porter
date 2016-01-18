<?php
/**
 * Views for Vanilla 2 export tools.
 *
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * HTML header.
 */
function pageHeader() {
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
                        <input class="InputBox" type="password" name="dbpass" value="<?php echo getValue('dbpass') ?>"/>
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
    echo '<h2>' . $supported[$platform]['name'] . '</h2>';
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
