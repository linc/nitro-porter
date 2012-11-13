<?php

function decho($Var, $Prefix = 'debug') {
   echo '<pre><b>'.$Prefix.'</b>: '.htmlspecialchars(print_r($Var, TRUE)).'</pre>';
}

function GenerateThumbnail($Path, $ThumbPath, $Height = 50, $Width = 50) {
   list($WidthSource, $HeightSource, $Type) = getimagesize($Path);
   
   if (!$WidthSource)
      return FALSE;
   
   if (!file_exists(dirname($ThumbPath)))
      mkdir(dirname($ThumbPath), 0777, TRUE);
   
   if ($WidthSource <= $Width && $HeightSource <= $Width) {
      copy($Path, $ThumbPath);
      return TRUE;
   }

   $XCoord = 0;
   $YCoord = 0;
   $HeightDiff = $HeightSource - $Height;
   $WidthDiff = $WidthSource - $Width;
   if ($WidthDiff > $HeightDiff) {
      // Crop the original width down
      $NewWidthSource = round(($Width * $HeightSource) / $Height);

      // And set the original x position to the cropped start point.
      $XCoord = round(($WidthSource - $NewWidthSource) / 2);
      $WidthSource = $NewWidthSource;
   } else {
      // Crop the original height down
      $NewHeightSource = round(($Height * $WidthSource) / $Width);

      // And set the original y position to the cropped start point.
      $YCoord = round(($HeightSource - $NewHeightSource) / 2);
      $HeightSource = $NewHeightSource;
   }

   switch ($Type) {
         case 1:
            $SourceImage = imagecreatefromgif($Path);
         break;
      case 2:
            $SourceImage = imagecreatefromjpeg($Path);
         break;
      case 3:
         $SourceImage = imagecreatefrompng($Path);
         imagealphablending($SourceImage, TRUE);
         break;
   }

   $TargetImage = imagecreatetruecolor($Width, $Height);
   imagecopyresampled($TargetImage, $SourceImage, 0, 0, $XCoord, $YCoord, $Width, $Height, $WidthSource, $HeightSource);
   imagedestroy($SourceImage);

   switch ($Type) {
      case 1:
         imagegif($TargetImage, $ThumbPath);
         break;
      case 2:
         imagejpeg($TargetImage, $ThumbPath);
         break;
      case 3:
         imagepng($TargetImage, $ThumbPath);
         break;
   }
   imagedestroy($TargetImage);
   return TRUE;
}

function ParseSelect($Sql) {
   if (!preg_match('`^\s*select\s+(.+)\s+from\s+(.+)\s*`is', $Sql, $Matches)) {
      trigger_error("Could not parse '$Sql'", E_USER_ERROR);
   }
   $Result = array('Select' => array(), 'From' => '');
   $Select = $Matches[1];
   $From = $Matches[2];
   
   // Replace commas within function calls.
   $Select = preg_replace_callback('`\(([^)]+?)\)`', '_ReplaceCommas', $Select);
//   echo($Select);
   $Parts = explode(',', $Select);
   
   $Selects = array();
   foreach ($Parts as $Expr) {
      $Expr = trim($Expr);
      $Expr = str_replace('!COMMA!', ',', $Expr);
      
      // Check for the star match.
      if (preg_match('`(\w+)\.\*`', $Expr, $Matches)) {
         $Result['Star'] = $Matches[1];
      }
      
      // Check for an alias.
      if (preg_match('`^(.*)\sas\s(.*)$`is', $Expr, $Matches)) {
//         decho($Matches, 'as');
         $Alias = trim($Matches[2], '`');
         $Selects[$Alias] = $Matches[1];
      } elseif (preg_match('`^[a-z_]?[a-z0-9_]*$`i', $Expr)) {
          // We are just selecting one column.
         $Selects[$Expr] = $Expr;
      } elseif (preg_match('`^[a-z_]?[a-z0-9_]*\.([a-z_]?[a-z0-9_]*)$`i', $Expr, $Matches)) {
         // We are looking at an alias'd select.
         $Alias = $Matches[1];
         $Selects[$Alias] = $Expr;
      } else {
         $Selects[] = $Expr;
      }
   }
   
   $Result['Select'] = $Selects;
   $Result['From'] = $From;
   $Result['Source'] = $Sql;
   return $Result;
}

function _ReplaceCommas($Matches) {
   return str_replace(',', '!COMMA!', $Matches[0]);
}

/**
 *
 * @param type $Sql
 * @param array $Columns An array in the form Alias => Column or just Column
 * @return type 
 */
function ReplaceSelect($Sql, $Columns) {
   if (is_string($Sql)) {
      $Parsed = ParseSelect($Sql);
   } else {
      $Parsed = $Sql;
   }
   
   // Set a prefix for new selects.
   if (isset($Parsed['Star']))
      $Px = $Parsed['Star'].'.';
   else
      $Px = '';
   
   $Select = $Parsed['Select'];
   
   $NewSelect = array();
   foreach ($Columns as $Index => $Value) {
      if (is_numeric($Index))
         $Alias = $Value;
      else
         $Alias = $Index;
      
      if (isset($Select[$Value])) {
         $NewSelect[$Alias] = $Select[$Value];
      } else {
         $NewSelect[$Alias] = $Px.$Value;
      }
   }
   $Parsed['Select'] = $NewSelect;
   
   if (is_string($Sql)) {
      return SelectString($Parsed);
   } else {
      return $Parsed;
   }
}

function SelectString($Parsed) {
   // Build the select.
   $Parts = $Parsed['Select'];
   $Selects = array();
   foreach ($Parts as $Alias => $Expr) {
      if (is_numeric($Alias) || $Alias == $Expr)
         $Selects[] = $Expr;
      else
         $Selects[] = "$Expr as `$Alias`";
   }
   $Select = implode(",\n  ", $Selects);
   
   $From = $Parsed['From'];
   
   $Result = "select\n  $Select\nfrom $From";
   return $Result;
}
?>