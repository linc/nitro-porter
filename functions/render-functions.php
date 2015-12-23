<?php
/**
 * Views for Vanilla 2 export tools.
 *
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * HTML header.
 */
function pageHeader() {
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <title>Vanilla Porter - Forum Export Tool</title>
    <link rel="stylesheet" type="text/css" href="style.css" media="screen"/>
</head>
<body>
<div id="Frame">
    <div id="Content">
        <div class="Title">
            <h1>
                <img src="http://vanillaforums.com/porter/vanilla_logo.png" alt="Vanilla"/>

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
function viewForm($Data) {
    $forums = getValue('Supported', $Data, array());
    $msg = getValue('Msg', $Data, '');
    $CanWrite = getValue('CanWrite', $Data, null);

    if ($CanWrite === null) {
        $CanWrite = testWrite();
    }
    if (!$CanWrite) {
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
                    <label>Source Forum Type</label>
                    <select name="type" id="ForumType" onchange="updatePrefix();">
                        <?php foreach ($forums as $forumClass => $forumInfo) : ?>
                            <option value="<?php echo $forumClass; ?>"<?php
                            if (getValue('type') == $forumClass) {
                                echo ' selected="selected"';
                            } ?>><?php echo $forumInfo['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </li>
                <li>
                    <label>Table Prefix <span>Most installations have a database prefix. If you&rsquo;re sure you don&rsquo;t have one, leave this blank.</span></label>
                    <input class="InputBox" type="text" name="prefix"
                           value="<?php echo htmlspecialchars(getValue('prefix')) != '' ? htmlspecialchars(getValue('prefix')) : $forums['vanilla1']['prefix']; ?>"
                           id="ForumPrefix"/>
                </li>
                <li>
                    <label>Database Host <span>Usually "localhost".</span></label>
                    <input class="InputBox" type="text" name="dbhost"
                           value="<?php echo htmlspecialchars(getValue('dbhost', '', 'localhost')) ?>"/>
                </li>
                <li>
                    <label>Database Name</label>
                    <input class="InputBox" type="text" name="dbname"
                           value="<?php echo htmlspecialchars(getValue('dbname')) ?>"/>
                </li>
                <li>
                    <label>Database Username</label>
                    <input class="InputBox" type="text" name="dbuser"
                           value="<?php echo htmlspecialchars(getValue('dbuser')) ?>"/>
                </li>
                <li>
                    <label>Database Password</label>
                    <input class="InputBox" type="password" name="dbpass" value="<?php echo getValue('dbpass') ?>"/>
                </li>
                <li>
                    <label>Export Type</label>
                    <select name="tables" id="ExportTables">
                        <option value="">All supported data</option>
                        <option value="User,Role,UserRole,Permission">Only users and roles</option>
                    </select>
                </li>
            </ul>
            <div class="Button">
                <input class="Button" type="submit" value="Begin Export"/>
            </div>
        </div>
    </form>
    <script type="text/javascript">
        //<![CDATA[
        function updatePrefix() {
            var type = document.getElementById('ForumType').value;
            switch (type) {
                <?php foreach($forums as $ForumClass => $ForumInfo) : ?>
                case '<?php echo $ForumClass; ?>':
                    document.getElementById('ForumPrefix').value = '<?php echo $ForumInfo['prefix']; ?>';
                    break;
                <?php endforeach; ?>
            }
        }
        //]]>
    </script>

    <?php pageFooter();
}

/**
 * Message: Result of export.
 *
 * @param array $Msgs Comments / logs from the export.
 * @param string $Class CSS class for wrapper.
 * @param string|bool $Path Path to file for download, or false.
 */
function viewExportResult($Msgs = array(), $Class = 'Info', $Path = false) {
    if (defined('CONSOLE')) {
        return;
    }

    pageHeader();

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
    pageFooter();
}

/**
 * Output a definition list of features for a single platform.
 *
 * @param string $Platform
 * @param array $Features
 */
function viewFeatureList($Platform, $Features = array()) {
    global $Supported;

    pageHeader();

    echo '<div class="Info">';
    echo '<h2>' . $Supported[$Platform]['name'] . '</h2>';
    echo '<dl>';

    foreach ($Features as $Feature => $Trash) {
        echo '
      <dt>' . featureName($Feature) . '</dt>
      <dd>' . featureStatus($Platform, $Feature) . '</dd>';
    }
    echo '</dl>';

    pageFooter();
}

/**
 * Output a table of features per all platforms.
 *
 * @param array $Features
 */
function viewFeatureTable($Features = array()) {
    global $Supported;
    $Platforms = array_keys($Supported);

    pageHeader();
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
        echo '<tr><td class="FeatureName">' . featureName($Feature) . '</td>';

        // Status per platform.
        foreach ($Platforms as $Platform) {
            echo '<td>' . featureStatus($Platform, $Feature, false) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table>';
    pageFooter();
}

?>
