<?php
/**
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Get the data support status for a single platform feature.
 *
 * @param $platform
 * @param $feature
 * @return string
 */
function featureStatus($platform, $feature, $notes = true) {
    global $supported;

    if (!isset($supported[$platform]['features'])) {
        return '<span class="No">No</span>';
    }

    $available = $supported[$platform]['features'];

    // Calculate feature availability.
    $status = '<span class="No">&#x2717;</span>';
    if (isset($available[$feature])) {
        if ($available[$feature] === 1) {
            $status = '<span class="Yes">&#x2713;</span>';
        } elseif ($available[$feature]) {
            if ($notes) {
                // Send the text of the note
                $status = $available[$feature];
            } else {
                // Say 'yes' for table shorthand
                $status = '<span class="Yes">&#x2713;</span>';
            }
        }
    }

    return $status;
}

/**
 * Insert spaces into a CamelCaseName => Camel Case Name.
 *
 * @param $feature
 * @return string
 */
function featureName($feature) {
    return ltrim(preg_replace('/[A-Z]/', ' $0', $feature));
}

/**
 * Define what data can be successfully ported to Vanilla.
 *
 * First array key is where the data is stored.
 * Second array key is the feature name, and value is one of:
 *    - 0 if unsupported
 *    - 1 if supported
 *    - string if supported, with notes or caveats
 *
 * @return array
 */
function vanillaFeatures($set = false) {
    if (!$set) {
        $set = array('core', 'addon');
    }

    $features = array();
    if (is_array($set)) {
        foreach ($set as $section) {
            $features += vanillaFeatureSet($section);
        }
    } else {
        $features = vanillaFeatureSet($set);
    }

    return $features;
}

/**
 * Get features by availability in Vanilla.
 *
 * @param string $section
 * @return array
 */
function vanillaFeatureSet($section) {
    switch ($section) {
        case 'addon':
            $set = array(
                'Tags' => 0,

            );
            break;
        case 'cloud':
            $set = array(
                'Badges' => 0,
                'Ranks' => 0,
                'Polls' => 0,
                'Groups' => 0,
            );
            break;
        case 'core':
        default:
            $set = array(
                'Comments' => 0,
                'Discussions' => 0,
                'Users' => 0,
                'Categories' => 0,
                'Roles' => 0,
                'Passwords' => 0,
                'Avatars' => 0,
                'PrivateMessages' => 0,
                'Signatures' => 0,
                'Attachments' => 0,
                'Bookmarks' => 0,
                'Permissions' => 0,
                //'UserWall'        => 0,
                'UserNotes' => 0,

                //'Emoji'           => 0,
            );
            break;
    }

    return $set;
}

?>
