<?php

/**
 * Error handler.
 *
 * @param $errno
 * @param $errstr
 */
function ErrorHandler($errno, $errstr) {
   if (defined(DEBUG) || ($errno != E_DEPRECATED && $errno != E_USER_DEPRECATED)) {
      echo "Error: ({$errno}) {$errstr}\n";
      die();
   }
}

/**
 * Debug echo tool.
 *
 * @param $Var
 * @param string $Prefix
 */
function decho($Var, $Prefix = 'debug') {
   echo '<pre><b>'.$Prefix.'</b>: '.htmlspecialchars(print_r($Var, TRUE)).'</pre>';
}

/**
 * Write out a value passed as bytes to its most readable format.
 */
function FormatMemorySize($Bytes, $Precision = 1) {
   $Units = array('B', 'K', 'M', 'G', 'T');

   $Bytes = max((int)$Bytes, 0);
   $Pow = floor(($Bytes ? log($Bytes) : 0) / log(1024));
   $Pow = min($Pow, count($Units) - 1);

   $Bytes /= pow(1024, $Pow);

   $Result = round($Bytes, $Precision).$Units[$Pow];
   return $Result;
}

/**
 * Creates URL codes containing only lowercase Roman letters, digits, and hyphens.
 * Converted from Gdn_Format::Url
 *
 * @param string $str A string to be formatted.
 * @return string
 */
function FormatUrl($Str) {
   $UrlTranslations = array('–' => '-', '—' => '-', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'Ae', 'Ä' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ą' => 'A', 'Ă' => 'A', 'Æ' => 'Ae', 'Ç' => 'C', 'Ć' => 'C', 'Č' => 'C', 'Ĉ' => 'C', 'Ċ' => 'C', 'Ď' => 'D', 'Đ' => 'D', 'Ð' => 'D', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ě' => 'E', 'Ĕ' => 'E', 'Ė' => 'E', 'Ĝ' => 'G', 'Ğ' => 'G', 'Ġ' => 'G', 'Ģ' => 'G', 'Ĥ' => 'H', 'Ħ' => 'H', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ī' => 'I', 'Ĩ' => 'I', 'Ĭ' => 'I', 'Į' => 'I', 'İ' => 'I', 'Ĳ' => 'IJ', 'Ĵ' => 'J', 'Ķ' => 'K', 'Ł' => 'K', 'Ľ' => 'K', 'Ĺ' => 'K', 'Ļ' => 'K', 'Ŀ' => 'K', 'Ñ' => 'N', 'Ń' => 'N', 'Ň' => 'N', 'Ņ' => 'N', 'Ŋ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'Oe', 'Ö' => 'Oe', 'Ō' => 'O', 'Ő' => 'O', 'Ŏ' => 'O', 'Œ' => 'OE', 'Ŕ' => 'R', 'Ŗ' => 'R', 'Ś' => 'S', 'Š' => 'S', 'Ş' => 'S', 'Ŝ' => 'S', 'Ť' => 'T', 'Ţ' => 'T', 'Ŧ' => 'T', 'Ț' => 'T', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'Ue', 'Ū' => 'U', 'Ü' => 'Ue', 'Ů' => 'U', 'Ű' => 'U', 'Ŭ' => 'U', 'Ũ' => 'U', 'Ų' => 'U', 'Ŵ' => 'W', 'Ý' => 'Y', 'Ŷ' => 'Y', 'Ÿ' => 'Y', 'Ź' => 'Z', 'Ž' => 'Z', 'Ż' => 'Z', 'Þ' => 'T', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'ae', 'ä' => 'ae', 'å' => 'a', 'ā' => 'a', 'ą' => 'a', 'ă' => 'a', 'æ' => 'ae', 'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ĉ' => 'c', 'ċ' => 'c', 'ď' => 'd', 'đ' => 'd', 'ð' => 'd', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ĕ' => 'e', 'ė' => 'e', 'ƒ' => 'f', 'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g', 'ģ' => 'g', 'ĥ' => 'h', 'ħ' => 'h', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ĩ' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i', 'ĳ' => 'ij', 'ĵ' => 'j', 'ķ' => 'k', 'ĸ' => 'k', 'ł' => 'l', 'ľ' => 'l', 'ĺ' => 'l', 'ļ' => 'l', 'ŀ' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ņ' => 'n', 'ŉ' => 'n', 'ŋ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'oe', 'ö' => 'oe', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o', 'ŏ' => 'o', 'œ' => 'oe', 'ŕ' => 'r', 'ř' => 'r', 'ŗ' => 'r', 'š' => 's', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'ue', 'ū' => 'u', 'ü' => 'ue', 'ů' => 'u', 'ű' => 'u', 'ŭ' => 'u', 'ũ' => 'u', 'ų' => 'u', 'ŵ' => 'w', 'ý' => 'y', 'ÿ' => 'y', 'ŷ' => 'y', 'ž' => 'z', 'ż' => 'z', 'ź' => 'z', 'þ' => 't', 'ß' => 'ss', 'ſ' => 'ss', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO', 'Ж' => 'ZH', 'З' => 'Z', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'ș' => 's', 'ț' => 't', 'Ț' => 'T',  'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SCH', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA', 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya');

   // Preliminary decoding
   $Str = strip_tags(html_entity_decode($Str, ENT_COMPAT, 'UTF-8'));
   $Str = strtr($Str, $UrlTranslations);
   $Str = preg_replace('`[\']`', '', $Str);

   // Test for Unicode PCRE support
   // On non-UTF8 systems this will result in a blank string.
   $UnicodeSupport = (preg_replace('`[\pP]`u', '', 'P') != '');

   // Convert punctuation, symbols, and spaces to hyphens
   if ($UnicodeSupport) {
      $Str = preg_replace('`[\pP\pS\s]`u', '-', $Str);
   } else {
      $Str = preg_replace('`[\s_[^\w\d]]`', '-', $Str);
   }

   // Lowercase, no trailing or repeat hyphens
   $Str = preg_replace('`-+`', '-', strtolower($Str));
   $Str = trim($Str, '-');

   return rawurlencode($Str);
}

/**
 * Test filesystem permissions.
 */
function TestWrite() {
   // Create file
   $file = 'vanilla2test.txt';
   @touch($file);
   if(is_writable($file)) {
      @unlink($file);
      return true;
   }
   else return false;
}

/**
 *
 *
 * @param $Key
 * @param null $Collection
 * @param string $Default
 * @return string
 */
function GetValue($Key, $Collection = NULL, $Default = '') {
   if(!$Collection)
      $Collection = $_POST;
   if(array_key_exists($Key, $Collection))
      return $Collection[$Key];
   return $Default;
}

/**
 * Create a thumbnail from an image file.
 *
 * @param $Path
 * @param $ThumbPath
 * @param int $Height
 * @param int $Width
 * @return bool
 */
function GenerateThumbnail($Path, $ThumbPath, $Height = 50, $Width = 50) {
   list($WidthSource, $HeightSource, $Type) = getimagesize($Path);

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

   try {
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
   }
   catch (Exception $e) {
      echo "Could not generate a thumnail for ".$TargetImage;
   }
}

/**
 *
 *
 * @param $Sql
 * @return array
 */
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

/**
 * Replace commas with a temporary placeholder.
 *
 * @param $Matches
 * @return mixed
 */
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

/**
 *
 *
 * @param $Parsed
 * @return string
 */
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

/**
 *
 *
 * @param $Paths
 * @param string $Delimiter
 * @return mixed
 */
function CombinePaths($Paths, $Delimiter = '/') {
   if (is_array($Paths)) {
      $MungedPath = implode($Delimiter, $Paths);
      $MungedPath = str_replace(array($Delimiter.$Delimiter.$Delimiter, $Delimiter.$Delimiter), array($Delimiter, $Delimiter), $MungedPath);
      return str_replace(array('http:/', 'https:/'), array('http://', 'https://'), $MungedPath);
   } else {
      return $Paths;
   }
}

/**
 * Take the template package, add our new name, and make a new package from it.
 *
 * @param string $Name
 */
function SpawnPackage($Name) {

   if ($Name && strlen($Name) > 2) {
      $Name = preg_replace('/[^A-Za-z0-9]/', '', $Name);
      $Template = file_get_contents(__DIR__.'/../tpl_package.txt');
      file_put_contents(__DIR__.'/../packages/'.$Name.'.php', str_replace('__NAME__', $Name, $Template));
      echo "Created new package: ".$Name."\n";
   }
   else {
      echo "Invalid name: 2+ alphanumeric characters only.";
   }
}
?>