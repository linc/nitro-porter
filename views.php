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
   echo '<?xml version="1.0" encoding="UTF-8"?>';
      ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
   <title>Vanilla Porter - Forum Export Tool</title>
   <link rel="stylesheet" type="text/css" href="style.css" media="screen" />
</head>
<body>
<div id="Frame">
	<div id="Content">
      <div class="Title">
         <h1>
            <img src="http://vanillaforums.com/porter/vanilla_logo.png" alt="Vanilla" />
            <p>Vanilla Porter <span class="Version">Version <?php echo APPLICATION_VERSION; ?></span></p>
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
function ViewForm($Data) {
   $forums = GetValue('Supported', $Data, array());
   $msg = GetValue('Msg', $Data, '');
   $Info = GetValue('Info', $Data, '');
   $CanWrite = GetValue('CanWrite', $Data, NULL);
   
   if($CanWrite === NULL)
      $CanWrite = TestWrite();
   if (!$CanWrite) {
      $msg = 'The porter does not have write permission to write to this folder. You need to give the porter permission to create files so that it can generate the export file.'.$msg;
   }
   
   if (defined('CONSOLE')) {
      echo $msg."\n";
      return;
   }

   PageHeader(); ?>
   <div class="Info">
      Welcome to the Vanilla Porter, an application for exporting your forum to the Vanilla 2 import format.
      For help using this application, 
      <a href="http://docs.vanillaforums.com/developers/importing/porter" style="text-decoration:underline;">see these instructions</a>.
   </div>
<form action="<?php echo $_SERVER['PHP_SELF'].'?'.http_build_query($_GET); ?>" method="post">
      <input type="hidden" name="step" value="info" />
      <div class="Form">
         <?php if($msg!='') : ?>
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
               <?php foreach($forums as $forumClass => $forumInfo) : ?>
                  <option value="<?php echo $forumClass; ?>"<?php 
                     if(GetValue('type') == $forumClass) echo ' selected="selected"'; ?>><?php echo $forumInfo['name']; ?></option>
               <?php endforeach; ?>
               </select>
            </li>
            <li>
               <label>Table Prefix <span>Most installations have a database prefix. If you&rsquo;re sure you don&rsquo;t have one, leave this blank.</span></label>
               <input class="InputBox" type="text" name="prefix" value="<?php echo htmlspecialchars(GetValue('prefix')) != '' ? htmlspecialchars(GetValue('prefix')) : $forums['vanilla1']['prefix']; ?>" id="ForumPrefix" />
            </li>
            <li>
               <label>Database Host <span>Usually "localhost".</span></label>
               <input class="InputBox" type="text" name="dbhost" value="<?php echo htmlspecialchars(GetValue('dbhost', '', 'localhost')) ?>" />
            </li>
            <li>
               <label>Database Name</label>
               <input class="InputBox" type="text" name="dbname" value="<?php echo htmlspecialchars(GetValue('dbname')) ?>" />
            </li>
            <li>
               <label>Database Username</label>
               <input class="InputBox" type="text" name="dbuser" value="<?php echo htmlspecialchars(GetValue('dbuser')) ?>" />
            </li>
            <li>
               <label>Database Password</label>
               <input class="InputBox" type="password" name="dbpass" value="<?php echo GetValue('dbpass') ?>" />
            </li>
         </ul>
         <div class="Button">
            <input class="Button" type="submit" value="Begin Export" />
         </div>
      </div>
   </form>
   <script type="text/javascript">
   //<![CDATA[
      function updatePrefix() {
         var type = document.getElementById('ForumType').value;
         switch(type) {
            <?php foreach($forums as $ForumClass => $ForumInfo) : ?>
            case '<?php echo $ForumClass; ?>': document.getElementById('ForumPrefix').value = '<?php echo $ForumInfo['prefix']; ?>'; break;
            <?php endforeach; ?>
         }
      }
   //]]>
   </script> 

   <?php PageFooter();
}


/**
 * Message: Result of export
 */
function ViewExportResult($Msgs = '', $Class = 'Info', $Path = '') {
   if (defined('CONSOLE')) {
      foreach($Msgs as $Msg) {
         
      }
      return;
   }
   
   PageHeader();
   if($Msgs) {
      // TODO: Style this a bit better.
      echo "<div class=\"$Class\">";
      foreach($Msgs as $Msg) {
         echo "<p>$Msg</p>\n";
      }
      echo "</div>";
      if($Path)
         echo "<a href=\"$Path\"><b>Download $Path</b></a>";
   }
   PageFooter();
}

function GetValue($Key, $Collection = NULL, $Default = '') {
   if(!$Collection)
      $Collection = $_POST;
   if(array_key_exists($Key, $Collection))
      return $Collection[$Key];
   return $Default;
}
?>