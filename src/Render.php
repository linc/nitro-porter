<?php

/**
 * HTML views for the export tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace NitroPorter;

class Render
{

    /**
     * Routing logic for which view to render.
     *
     * @return void
     */
    public static function route()
    {
        if (isset($_REQUEST['list'])) {
            Render::viewFeatureList($_REQUEST['list']); // Single package feature list.
        } elseif (isset($_REQUEST['features'])) {
            Render::viewFeatureTable();  // Overview table.
        } else {
            Render::viewForm(); // Starting Web UI.
        }
    }

    /**
     * HTML header.
     */
    public static function pageHeader()
    {
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Nitro Porter</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" type="text/css" href="assets/style.css" media="screen"/>
</head>
<body>
<div id="Frame">
    <div id="Content">
    ';
    }

    /**
     * HTML footer.
     */
    public static function pageFooter()
    {
        echo "\n    </div>\n</div>\n</body>\n</html>";
    }

    /**
     * Form: Database connection info.
     */
    public static function viewForm($data = [])
    {
        $forums = \NitroPorter\SupportManager::getInstance()->getSupportList();
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

        self::pageHeader(); ?>
    <p class="Info">
        Need help? Try the
        <a href="https://success.vanillaforums.com/kb/articles/150-vanilla-porter-guide" target="_blank">guide</a> and
        <a href="?features=1">feature support table</a>.
    </p>
    <div class="Title">
        <h1>Nitro Porter</h1>
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
            <h2>Source</h2>
            <ul>
                <li>
                    <label>Connection
                        <select name="source_connection" >
                            <option disabled selected value> — selection required — </option>
                            <?php foreach (getSourceConnections() as $id => $name) {
                                echo '<option value="' . $id . '">' . $name . '</option>';
                            } ?>
                            <!--<option disabled>API</option>
                            <option disabled>CSV</option>-->
                        </select>
                    </label>
                </li>
                <li>
                    <label>Package
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
                    <label>Database Table Prefix
                        <input class="InputBox" type="text" name="prefix" placeholder="optional"
                            value="<?php echo htmlspecialchars(getValue('prefix')) != ''
                                ? htmlspecialchars(getValue('prefix')) : ''; ?>"
                            id="ForumPrefix"/>
                    </label>
                </li>
            </ul>
            <h2>Target</h2>
            <ul>
                <li>
                    <label>Package
                        <select name="target_type" id="TargetType" onchange="setTarget()">
                            <option disabled selected value> — selection required — </option>
                            <option value="Flarum">Flarum (MySQL)</option>
                            <option value="Vanilla">Vanilla Forums (custom CSV)</option>
                        </select>
                    </label>
                </li>
                <li id="TargetConnection" style="display:none;">
                    <label>Connection
                        <select name="target_connection" >
                            <?php foreach (getSourceConnections() as $id => $name) {
                                echo '<option value="' . $id . '">' . $name . '</option>';
                            } ?>
                        </select>
                    </label>

                    <p class="Warnings">Any existing Flarum data in the target connection will be overwritten.</p>
                </li>
                <li style="display:none;">
                    <label>Table Prefix <span>(optional)</span>
                        <input class="InputBox" type="text" name="target_prefix" value="" id="TargetPrefix"/>
                    </label>
                </li>
            </ul>
            <h2>Transfer</h2>
            <ul>
                <li>
                    <label>Data
                    <select name="tables" id="ExportTables">
                        <option value="">All supported data</option>
                        <option value="User,Role,UserRole,Permission">Users and roles</option>
                        <option value="User,Category,Discussion,Comment">Users, categories, and discussions</option>
                    </select>
                    </label>
                </li>
                <li id="FileExports">
                    <label><input type="checkbox" name="avatars" value="1">
                        Avatars
                    </label>
                    <label><input type="checkbox" name="files" value="1">
                        Attachments
                    </label>
                </li>
            </ul>
            <div class="Button">
                <input class="Button" type="submit" value="Start"/>
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
    function setTarget() {
        let target = document.getElementById('TargetType').value;
        switch (target) {
            case 'Flarum':
                document.getElementById("TargetConnection").style.display = 'block';
                break;
            default:
                document.getElementById("TargetConnection").style.display = 'none';
        }
    }
    </script>

        <?php self::pageFooter();
    }

    /**
     * Message: Result of export.
     *
     * @param array       $msgs  Comments / logs from the export.
     * @param string      $class CSS class for wrapper.
     * @param string|bool $path  Path to file for download, or false.
     */
    public static function viewExportResult($msgs = array(), $class = 'Info', $path = false)
    {
        if (defined('CONSOLE')) {
            return;
        }

        self::pageHeader();

        echo "<p class=\"DownloadLink\">Success!";
        if ($path) {
            " <a href=\"$path\"><b>Download exported file</b></a>";
        }
        echo "</p>";

        if (count($msgs)) {
            echo "<div class=\"$class\">";
            echo "<p>Really boring export logs follow:</p>\n";
            echo '<ol>';
            foreach ($msgs as $msg) {
                echo "<li>$msg</li>\n";
            }
            echo '</ol>';
            echo "<p>It worked! You&rsquo;re free! Sweet, sweet victory.</p>\n";
            echo "</div>";
        }
        self::pageFooter();
    }

    /**
     * Output a definition list of features for a single platform.
     *
     * @param string $platform
     * @param array  $features
     */
    public static function viewFeatureList($platform)
    {
        $supported = \NitroPorter\SupportManager::getInstance()->getSupportList();
        $features = \NitroPorter\SupportManager::getInstance()->vanillaFeatures();
        self::pageHeader();

        echo '<p class="Info"><a href="/?features=1">&larr; Back</a></p>';
        echo '<h2>' . htmlspecialchars($supported[$platform]['name']) . '</h2>';

        echo '<dl class="Info">';

        foreach ($features as $feature => $trash) {
            echo '
          <dt>' . self::featureName($feature) . '</dt>
          <dd>' . \NitroPorter\SupportManager::getInstance()->featureStatus($platform, $feature) . '</dd>';
        }
        echo '</dl>';

        self::pageFooter();
    }

    /**
     * Output a table of features per all platforms.
     *
     * @param array $features
     */
    public static function viewFeatureTable()
    {
        $features = \NitroPorter\SupportManager::getInstance()->vanillaFeatures();
        $supported = \NitroPorter\SupportManager::getInstance()->getSupportList();
        $platforms = array_keys($supported);

        self::pageHeader();

        echo '<p class="Info">Click a platform to zoom in or <a href="/">go back</a></p>';

        echo '<h1 class="FeatureTitle">Supported Features</h1>';
        echo '<table class="Features"><thead><tr>';

        // Header row of labels for each platform
        echo '<th></th>';
        foreach ($features as $feature => $trash) {
            echo '<th class="FeatureName"><div><span>'
                 . self::featureName($feature) . '</span></div></th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($platforms as $slug) {
            echo '<tr><td class="Platform"><span><a href="?list='
                 . $slug . '">' . $supported[$slug]['name'] . '</a></span></td>';

            // Status per platform.
            foreach ($features as $feature => $trash) {
                echo '<td>' .
                    \NitroPorter\SupportManager::getInstance()->featureStatus($slug, $feature, false) .
                '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody></table>';
        self::pageFooter();
    }

    /**
     * Insert spaces into a CamelCaseName => Camel Case Name.
     *
     * @param  $feature
     * @return string
     */
    public static function featureName($feature)
    {
        return ltrim(preg_replace('/[A-Z]/', ' $0', $feature));
    }
}
