<?php
/**
 * Filter functions for passing thru values during export.
 *
 * @copyright Vanilla Forums Inc. 2010-2015
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

 /**
 * Don't allow zero-equivalent dates.
 *
 * @param $Value
 * @return string
 */
function ForceDate($Value) {
   if (!$Value || preg_match('`0000-00-00`', $Value)) {
      return gmdate('Y-m-d H:i:s');
   }
   return $Value;
}

/**
 * Only allow IPv4 addresses to pass.
 *
 * @param $ip
 * @return string|null Valid IPv4 address or nuthin'.
 */
function ForceIP4($ip) {
   if (preg_match('`(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})`', $ip, $m))
      $ip = $m[1];
   else
      $ip = null;

   return $ip;
}

/**
 * Decode the HTML out of a value.
 */
function HTMLDecoder($Value) {
   return html_entity_decode($Value, ENT_QUOTES, 'UTF-8');
}

/**
 * Inverse int value.
 *
 * @param $Value
 * @return int
 */
function NotFilter($Value) {
   return (int)(!$Value);
}

/**
 * Convert a timestamp to MySQL date format.
 *
 * Do this in MySQL with FROM_UNIXTIME() instead whenever possible.
 *
 * @param $Value
 * @return null|string
 */
function TimestampToDate($Value) {
   if ($Value == NULL)
      return NULL;
   else
      return gmdate('Y-m-d H:i:s', $Value);
}

/**
 * Wrapper for long2ip that nulls 'false' values.
 *
 * @param $Value
 * @return null|string
 */
function long2ipf($Value) {
   if (!$Value)
      return NULL;
   return long2ip($Value);
}

?>