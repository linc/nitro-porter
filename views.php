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
   <title>Vanilla 2 Forum Export Tool</title>
   <link rel="stylesheet" type="text/css" href="./design/style.css" media="screen" />
</head>
<body>
<div id="Frame">
	<div id="Content">
      <div class="Title">
         <h1>
            <img src="./design/vanilla_logo.png" alt="Vanilla" />
            <p>Forum Export Tool</p>
         </h1>
      </div>
   <?php
}

   
/**
 * HTML footer
 */
function PageFooter() {
   ?>
   </div>
</div>
</body>
</html><?php

}

   
/**
 * Message: Write permission fail
 */
function ViewNoPermission($msg) {
   PageHeader(); ?>
   <div class="Messages Errors">
      <ul>
         <li><?php echo $msg; ?></li>
      </ul>
   </div>
   
   <?php PageFooter();
}

   
/**
 * Form: Database connection info
 */
function ViewForm($Forums, $Msg='', $Info = '') {
   PageHeader(); ?>
   <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
      <input type="hidden" name="step" value="info" />
      <div class="Form">
         <?php if($Msg != '') : ?>
         <div class="Messages Errors">
            <ul>
               <li><?php echo $Msg; ?></li>
            </ul>
         </div>
         <?php endif; ?>   
         <ul>
            <li>
               <label>Source Forum Type</label>
               <select name="type">
               <?php foreach($Forums as $forumClass => $forumInfo) : ?>
                  <option value="<?php echo $forumClass; ?>"<?php 
                     if(is_array($Info) && $Info['type']==$forumClass) 
                        echo ' selected="selected"'; ?>><?php echo $forumInfo['name']; ?></option>
               <?php endforeach; ?>
               </select>
            </li>
            <li>
               <label>Table Prefix <span>Table prefix is not required</span></label>
               <input class="InputBox" type="text" name="prefix" value="<?php echo (is_array($Info)) ? $Info['prefix'] : $forumInfo['prefix']; ?>" />
            </li>
            <li>
               <label>Database Host <span>Database host is usually "localhost"</span></label>
               <input class="InputBox" type="text" name="dbhost" value="<?php echo (is_array($Info)) ? $Info['dbhost'] :'localhost'; ?>" />
            </li>
            <li>
               <label>Database Name</label>
               <input class="InputBox" type="text" name="dbname" value="<?php if(is_array($Info)) echo $Info['dbname']; ?>" />
            </li>
            <li>
               <label>Database Username</label>
               <input class="InputBox" type="text" name="dbuser" value="<?php if(is_array($Info)) echo $Info['dbuser']; ?>" />
            </li>
            <li>
               <label>Database Password</label>
               <input class="InputBox" type="password" name="dbpass" />
            </li>
         </ul>
         <div class="Button">
            <input class="Button" type="submit" value="Begin Export" />
         </div>
      </div>
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
   
   
