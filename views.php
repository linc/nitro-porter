<?php
/**
 * Views for Vanilla 2 export tools
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
 
   
/**
 * HTML header
 */
function PageHeader() {
   ?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
   <title>Vanilla 2 forum export tool</title>
   <style>
   
   </style>
</head>
<body>
   <h1>Vanilla 2 forum export tool</h1>
   <?php

}

   
/**
 * HTML footer
 */
function PageFooter() {
   ?>
</body>
</html><?php

}

   
/**
 * Message: Write permission fail
 */
function ViewNoPermission($msg) {
   PageHeader(); ?>
   
   <p id="message"><?php echo $msg; ?></p>
   
   <?php PageFooter();
}

   
/**
 * Form: Database connection info
 */
function ViewForm($forums, $msg='') {
   PageHeader(); ?>
   
   <?php if($msg!='') : ?>
   
   <p id="message"><?php echo $msg; ?></p>
   
   <?php endif; ?>   
   
   <fieldset>
   <input type="hidden" name="step" value="info" />
   <ul>
      <li><label>Forum type</label>
         <select name="type">
         <?php foreach($forums as $forumClass => $forumInfo) : ?>
            <option value="<?php echo $forumClass; ?>"><?php echo $forumInfo['name']; ?></option>
         <?php endforeach; ?>
         </select>
      </li>
      <li><label>Database prefix</label><input type="text" name="prefix" value="<?php echo $forumInfo['prefix']; ?>" /> Leave blank if none.</li>
      <li><label>Database host</label><input type="text" name="dbhost" value="localhost" /> Usually &ldquo;localhost&rdquo; if you&rsquo;re not sure.</li>
      <li><label>Database name</label><input type="text" name="dbname" value="" /></li>
      <li><label>Database username</label><input type="text" name="dbuser" value="" /></li>
      <li><label>Database password</label><input type="password" name="dbpass" /></li>
   </ul>
   <input class="button" type="submit" value="Begin Export" />
   </fieldset>
   </form>

   <?php PageFooter();
}


/**
 * Message: Result of export
 */
function ViewExportResult($msg='') {
   PageHeader(); ?>
   
   
   
   <?php PageFooter();
}
   
   
