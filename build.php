<?php
/**
 * One-step build process for single-file export tools
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/** @var array Filename => lines to skip */
$corefiles = array(
   'class.exportcontroller.php'=>10, 
   'class.exportmodel.php'=>10);

/** @var array Finished packages */
$packages = array(
   'vbulletin');

