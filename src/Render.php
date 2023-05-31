<?php

/**
 * HTML views for the export tool.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace Porter;

class Render
{
    /**
     * Output help to the CLI.
     */
    public static function cliHelp()
    {
        $options = Request::instance()->getAllOptions(true);

        $output = '';

        foreach ($options as $section => $options) {
            $output .= $section . "\n";

            foreach ($options as $longname => $properties) {
                $output .= self::cliSingleOption($longname, $properties) . "\n";
            }

            $output .= "\n";
        }

        // Give the user possible values for the required parameters.
        $output .= "Valid packages: " .
            implode(', ', array_keys(Support::getInstance()->getSources())) . "\n\n";

        $output .= "Valid outputs: " .
            implode(', ', array_keys(Support::getInstance()->getTargets())) . "\n\n";

        $output .= "Available sources/targets in config.php:" . "\n";
        foreach (\Porter\Config::getInstance()->getConnections() as $c) {
            $output .= str_pad('  ' . $c['alias'], 20, ' ') . '(' . $c['user'] . '@' . $c['name'] . ')' . "\n";
        }

        $output .= "\nExample command (Vanilla to Flarum):\n  porter -p=Vanilla2 -o=Flarum -s=source -t=test\n";

        echo $output;
    }

    /**
     * Build a single line of help output for a single CLI command.
     *
     * @param string $longname
     * @param array $properties
     * @return string
     */
    public static function cliSingleOption(string $longname, array $properties): string
    {
        // Indent.
        $output = "  ";

        // Short code.
        if (isset($properties['Short'])) {
            $output .= '-' . $properties['Short'] . ', ';
        }

        // Long code.
        $output .= "--$longname";

        // Align descriptions by padding.
        $output = str_pad($output, 20, ' ');

        // Whether param is required.
        if (v('Req', $properties)) {
            $output .= 'Required. ';
        }

        // Description.
        $output .= "{$properties[0]}";

        // List valid values for --type.
        if ($values = v('Values', $properties)) {
            //$output .= ' (Choose from: ' . implode(', ', $values) . ')';
        }

        return $output;
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
    public static function viewForm(Request $request)
    {
        $sources = \Porter\Support::getInstance()->getSources();
        $targets = \Porter\Support::getInstance()->getTargets();
        $msg = getValue('Msg', $request->getAll(), '');
        $canWrite = testWrite();
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
        <a href="https://nitroporter.org" target="_blank">guide</a>.
        Supported <a href="?sources=1">sources</a> &amp; <a href="?targets=1">targets</a>.
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
                        <select name="source" >
                            <option disabled selected value> — selection required — </option>
                            <?php foreach (self::getFormattedConnectionList() as $id => $name) {
                                echo '<option value="' . $id . '">' . $name . '</option>';
                            } ?>
                            <!--<option disabled>API</option>
                            <option disabled>CSV</option>-->
                        </select>
                    </label>
                </li>
                <li>
                    <label>Format
                        <select name="package" id="ForumType" onchange="setPrefix()">
                            <option disabled selected value> — selection required — </option>
                            <?php foreach ($sources as $alias => $info) : ?>
                                <option value="<?php echo $alias; ?>"><?php echo $info['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </li>
                <li>
                    <label>Database Table Prefix
                        <input class="InputBox" type="text" name="src-prefix" placeholder="optional"
                            value="<?php echo htmlspecialchars(getValue('src-prefix')) != ''
                                ? htmlspecialchars(getValue('src-prefix')) : ''; ?>"
                            id="ForumPrefix"/>
                    </label>
                </li>
            </ul>
            <h2>Target</h2>
            <ul>
                <li>
                    <label>Output
                        <select name="output" id="TargetType" onchange="setTarget()">
                            <option disabled selected value> — selection required — </option>
                            <?php foreach ($targets as $alias => $info) : ?>
                                <option value="<?php echo $alias; ?>"><?php echo $info['name']; ?></option>
                            <?php endforeach; ?>
                            <option value="file">Vanilla Forums (file)</option>
                        </select>
                    </label>
                </li>
                <li id="TargetConnection" style="display:none;">
                    <label>Connection
                        <select name="target" >
                            <option disabled selected value> — selection required — </option>
                            <?php foreach (self::getFormattedConnectionList() as $id => $name) {
                                echo '<option value="' . $id . '">' . $name . '</option>';
                            } ?>
                        </select>
                    </label>

                    <p class="Warnings">Any existing Flarum data in the target connection will be overwritten.</p>
                </li>
                <li style="display:none;">
                    <label>Table Prefix <span>(optional)</span>
                        <input class="InputBox" type="text" name="tar-prefix" value="" id="TargetPrefix"/>
                    </label>
                </li>
            </ul>
            <!--<h2>Transfer</h2>
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
                <li id="vBulletin-files" style="display:none;">
                    <label><input type="checkbox" name="db-avatars" value="1">
                        Avatars in database
                    </label>
                    <label><input type="checkbox" name="db-files" value="1">
                        Attachments in database
                    </label>
                </li>
            </ul>-->
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
        foreach ($sources as $forumClass => $forumInfo) {
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
            case 'Waterhole':
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
     * Result of export.
     *
     * @param array $comments Log of the export.
     */
    public static function viewResult(array $comments)
    {
        self::pageHeader();
        echo "<p class=\"DownloadLink\">Success!</p>";

        if (count($comments)) {
            echo "<div class=\"Info\">";
            echo "<p>Really boring export logs follow:</p>\n";
            echo '<ol>';
            foreach ($comments as $msg) {
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
     * @param Request $request
     */
    public static function viewFeatureList(Request $request)
    {
        if ($request->get('showsource')) {
            $platform = $request->get('showsource');
            $supported = \Porter\Support::getInstance()->getSources();
        } else {
            $platform = $request->get('showtarget');
            $supported = \Porter\Support::getInstance()->getTargets();
        }
        $features = \Porter\Support::getInstance()->getAllFeatures();
        self::pageHeader();

        echo '<p class="Info"><a href="/?sources=1">&larr; Back</a></p>';
        echo '<h2>' . htmlspecialchars($supported[$platform]['name']) . '</h2>';

        echo '<dl class="Info">';

        foreach ($features as $feature => $trash) {
            echo '
          <dt>' . self::featureName($feature) . '</dt>
          <dd>' . \Porter\Support::getInstance()->getFeatureStatusHtml($supported, $platform, $feature) . '</dd>';
        }
        echo '</dl>';

        self::pageFooter();
    }

    /**
     * Display table of source package features.
     */
    public static function viewSourcesTable()
    {
        self::viewFeatureTable('sources');
    }

    /**
     * Display table of target package features.
     */
    public static function viewTargetsTable()
    {
        self::viewFeatureTable('targets');
    }

    /**
     * Output a table of features per all platforms.
     *
     * @param string $type One of 'sources' or 'targets'.
     */
    public static function viewFeatureTable(string $type = 'sources')
    {
        if (!in_array($type, ['sources', 'targets'])) {
            return;
        }

        $features = \Porter\Support::getInstance()->getAllFeatures();
        $method = 'get' . $type;
        $supported = \Porter\Support::getInstance()->$method();
        $packages = array_keys($supported);

        self::pageHeader();

        echo '<p class="Info">Click a platform to zoom in or <a href="/">go back</a></p>';

        echo '<h1 class="FeatureTitle">Supported ' . ucfirst($type) . ' Data</h1>';
        echo '<table class="Features"><thead><tr>';

        // Header row of labels for each platform
        echo '<th></th>';
        foreach ($features as $feature => $trash) {
            echo '<th class="FeatureName"><div><span>'
                 . self::featureName($feature) . '</span></div></th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($packages as $package) {
            echo '<tr><td class="Platform"><span><a href="?show' . rtrim($type, 's') . '='
                 . $package . '">' . $supported[$package]['name'] . '</a></span></td>';

            // Status per platform.
            foreach ($features as $feature => $trash) {
                echo '<td>' .
                    \Porter\Support::getInstance()->getFeatureStatusHtml($supported, $package, $feature, false) .
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
     * @param string $feature
     * @return string
     */
    public static function featureName($feature)
    {
        return ltrim(preg_replace('/[A-Z]/', ' $0', $feature));
    }

    /**
     * Get connections list formatted for a dropdown.
     */
    public static function getFormattedConnectionList()
    {
        $prepared_connections = [];
        foreach (\Porter\Config::getInstance()->getConnections() as $c) {
            $prepared_connections[$c['alias']] = $c['alias'] . ' (' . $c['user'] . '@' . $c['name'] . ')';
        }
        return $prepared_connections;
    }
}
