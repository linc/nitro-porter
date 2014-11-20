<?php
/**
 * @copyright Vanilla Forums Inc. 2010-2015
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Get the data support status for a single platform feature.
 *
 * @param $Platform
 * @param $Feature
 * @return string
 */
function FeatureStatus($Platform, $Feature, $Notes = TRUE) {
   global $Supported;

   if (!isset($Supported[$Platform]['features'])) {
      return '<span class="No">No</span>';
   }

   $Available = $Supported[$Platform]['features'];

   // Calculate feature availability.
   $Status = '<span class="No">&#x2717;</span>';
   if (isset($Available[$Feature])) {
       if ($Available[$Feature] === 1) {
         $Status = '<span class="Yes">&#x2713;</span>';
       }
       elseif ($Available[$Feature]) {
         if ($Notes) {
            // Send the text of the note
            $Status = $Available[$Feature];
         }
         else {
            // Say 'yes' for table shorthand
            $Status = '<span class="Yes">&#x2713;</span>';
         }
       }
   }

   return $Status;
}

/**
 * Insert spaces into a CamelCaseName => Camel Case Name.
 *
 * @param $Feature
 * @return string
 */
function FeatureName($Feature) {
   return ltrim(preg_replace('/[A-Z]/', ' $0', $Feature));
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
function VanillaFeatures($Set = FALSE) {
   if (!$Set) {
      $Set = array('core', 'addon');
   }

   $Features = array();
   if (is_array($Set)) {
      foreach ($Set as $Section) {
         $Features += VanillaFeatureSet($Section);
      }
   }
   else {
      $Features = VanillaFeatureSet($Set);
   }

   return $Features;
}

/**
 * Get features by availability in Vanilla.
 *
 * @param string $Section
 * @return array
 */
function VanillaFeatureSet($Section) {
   switch ($Section) {
      case 'addon':
         $Set = array(
            //'Tags'            => 0,

            );
         break;
      case 'cloud':
         $Set = array(
            'Badges'          => 0,
            'Ranks'           => 0,
            'Polls'           => 0,
            'Groups'          => 0,
            );
         break;
      case 'core':
      default:
         $Set = array(
            'Comments'        => 0,
            'Discussions'     => 0,
            'Users'           => 0,
            'Categories'      => 0,
            'Roles'           => 0,
            'Passwords'       => 0,
            'Avatars'         => 0,
            'PrivateMessages' => 0,
            'Signatures'      => 0,
            'Attachments'     => 0,
            'Bookmarks'       => 0,
            'Permissions'     => 0,
            //'UserWall'        => 0,
            'UserNotes'       => 0,

            //'Emoji'           => 0,
            );
         break;
    }

    return $Set;
}
?>