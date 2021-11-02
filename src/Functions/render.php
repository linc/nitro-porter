<?php
/**
 * HTML views for the export tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

/**
 * HTML header.
 */
function pageHeader()
{
    ?>
<!DOCTYPE html>
<html>
<head>
    <title>Nitro Porter - Forum Export Tool</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" type="text/css" href="assets/style.css" media="screen"/>
</head>
<body>
<div id="Frame">
    <div id="Content">
        <div class="Title">
            <h1>
                <p>Nitro Porter <span class="Version">Version <?php echo VERSION; ?></span></p>
            </h1>
        </div>
    <?php
}

/**
 * HTML footer.
 */
function pageFooter()
{
    ?>
    </div>
</div>
</body>
</html><?php
}

/**
 * Form: Database connection info.
 */
function viewForm($data = [])
{
    $forums = getSupportList();
    $msg = getValue('Msg', $data, '');
    $canWrite = testWrite();

    if ($canWrite === null) {
        $canWrite = testWrite();
    }
    if (!$canWrite) {
        $msg = 'The porter does not have write permission to write to this folder. '
        . 'You need to give the porter permission to create files so that it can generate the export file.' . $msg;
    }

    if (defined('CONSOLE')) {
        echo $msg . "\n";

        return;
    }

    pageHeader(); ?>
    <div class="Info">
        Need help?
        <a href="https://success.vanillaforums.com/kb/articles/150-vanilla-porter-guide"
            style="text-decoration:underline;"
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
                        Source Package
                        <select name="type" id="ForumType" onchange="setPrefix()">
                            <option disabled selected value> — selection required — </option>
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
                    <label>Table Prefix <span>Most installations have a database prefix.
                        If you&rsquo;re sure you don&rsquo;t have one, leave this blank.</span>
                        <input class="InputBox" type="text" name="prefix"
                            value="<?php echo htmlspecialchars(getValue('prefix')) != ''
                                ? htmlspecialchars(getValue('prefix')) : ''; ?>"
                            id="ForumPrefix"/>
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
    function setPrefix() {
        let type = document.getElementById('ForumType').value;
        switch (type) {
        <?php
        foreach ($forums as $forumClass => $forumInfo) {
            $hasAvatars = !empty($forumInfo['features']['Avatars']);
            $hasAttachments = !empty($forumInfo['features']['Attachments']);
            $spacer = "\n                "; // Makes the JS legible in Inpsector.

            $exportOptions = $spacer
                 . "document.getElementsByName('avatars')[0].disabled = "
                 . ($hasAvatars ? 'false' : 'true') . "; ";
            if ($hasAvatars) {
                $exportOptions .= $spacer
                 . "document.getElementsByName('avatars')[0].parentNode.classList.remove('disabled'); ";
            } else {
                $exportOptions .= $spacer
                 . "document.getElementsByName('avatars')[0].parentNode.classList.add('disabled'); ";
            }
            $exportOptions .= $spacer . "document.getElementsByName('files')[0].disabled = "
                 . ($hasAttachments ? 'false' : 'true') . "; ";
            if ($hasAttachments) {
                $exportOptions .= $spacer
                 . "document.getElementsByName('files')[0].parentNode.classList.remove('disabled'); ";
            } else {
                $exportOptions .= $spacer
                 . "document.getElementsByName('files')[0].parentNode.classList.add('disabled'); ";
            }
            ?>

            case '<?php echo $forumClass; ?>':<?php echo $exportOptions . "\n"; ?>
                document.getElementById("ForumPrefix").value = '<?php echo $forumInfo['prefix']; ?>';
                break;
        <?php } ?>

        }
    }
    </script>

    <?php pageFooter();
}

/**
 * Message: Result of export.
 *
 * @param array       $msgs  Comments / logs from the export.
 * @param string      $class CSS class for wrapper.
 * @param string|bool $path  Path to file for download, or false.
 */
function viewExportResult($msgs = array(), $class = 'Info', $path = false)
{
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
 * @param array  $features
 */
function viewFeatureList($platform)
{
    $supported = getSupportList();
    $features = vanillaFeatures();
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
function viewFeatureTable()
{
    $features = vanillaFeatures();
    $supported = getSupportList();
    $platforms = array_keys($supported);

    pageHeader();
    echo '<h2 class="FeatureTitle">Data currently supported per platform</h2>';
    echo '<p>Click any platform name for details, or <a href="/" style="text-decoration:underline;">go back</a>.</p>';
    echo '<table class="Features"><thead><tr>';

    // Header row of labels for each platform
    echo '<th><i>Feature</i></th>';
    foreach ($platforms as $slug) {
        echo '<th class="Platform"><div><span><a href="?list='
             . $slug . '">' . $supported[$slug]['name'] . '</a></span></div></th>';
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
