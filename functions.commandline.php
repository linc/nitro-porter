<?php

$GlobalOptions = array(
   'host' => array('Host to connect to (IP address or hostname).', 'Req' => TRUE, 'Sx' => ':', 'Field' => 'dbhost', 'Default' => '127.0.0.1'),
   'dbname' => array('The name of the database.', 'Req' => TRUE, 'Sx' => ':', 'Field' => 'dbname'),
   'user' => array('The username of the database.', 'Req' => TRUE, 'Sx' => ':', 'Field' => 'dbuser', 'Short' => 'u'),
   'password' => array('The password to use when connecting to the server.', 'Sx' => '::', 'Field' => 'dbpass', 'Short' => 'p', 'Default' => ''),
   'type' => array('The type of forum to export from.', 'Req' => TRUE, 'Sx' => ':', 'Field' => 'type'),
   'avatars' => array('Whether or not to export avatars.', 'Sx' => '::', 'Field' => 'avatars', 'Short' => 'a', 'Default' => ''),
   'prefix' => array('The table prefix in the database.', 'Field' => 'prefix', 'Sx' => ':', 'Default' => ''),
   'cdn' => array('The prefix to be applied to uploaded file links.', 'Field' => 'cdn', 'Sx' => ':', 'Default' => ''),
   'help' => array('Show help.')
);

// Go through all of the supported types and add them to the type description.
$GlobalOptions['type']['Values'] = array_keys($Supported);

function GetAllCommandLineOptions($Sections = FALSE) {
   global $GlobalOptions, $Supported;
   
   if ($Sections)
      $Result['Global Options'] = $GlobalOptions;
   else
      $Result = $GlobalOptions;
   
   foreach ($Supported as $Type => $Options) {
      $CommandLine = V('CommandLine', $Options);
      if (!$CommandLine)
         continue;
      
      if ($Sections) {
         $Result[$Options['name']] = $CommandLine;
      } else {
         // We need to add the types to each command line option for validation purposes.
         foreach ($CommandLine as $LongCode => $Row) {
            if (isset($Result[$LongCode])) {
               $Result[$LongCode]['Types'][] = $Type;
            } else {
               $Row['Types'] = array($Type);
               $Result[$LongCode] = $Row;
            }
         }
      }
   }
   
   return $Result;
}

function GetOptCodes($Options) {
   $ShortCodes = '';
   $LongCodes = array();
   
   foreach ($Options as $LongCode => $Row) {
      $Sx = V('Sx', $Row, '');
      $Short = V('Short', $Row, '');
      
      if ($Short)
         $ShortCodes .= $Short.$Sx;
      $LongCodes[] = $LongCode.$Sx;
   }
   
   return array($ShortCodes, $LongCodes);
}

function ParseCommandLine() {
   $CommandOptions = GetAllCommandLineOptions();
   list($ShortCodes, $LongCodes) = GetOptCodes($CommandOptions);
   
//   print_r($LongCodes);
   
   $Opts = getopt($ShortCodes, $LongCodes);
   
   if (isset($Opts['help'])) {
      WriteCommandLineHelp();
      die();
   }
   
   $Opts = ValidateCommandLine($Opts, $CommandOptions);
   
   if ($Opts === FALSE)
      die();
   
   $_POST = $Opts;
}

function ValidateCommandLine($Values, $Options) {
   $Errors = array();
   $Result = array();
   
//   print_r($Values);
//   print_r($Options);
   
   $Type = V('type', $Values, V('t', $Values));
   
   foreach ($Options as $LongCode => $Row) {
      $Req = V('Req', $Row);
      $Short = V('Short', $Row);
      
      $Sx = V('Sx', $Row);
      $Types = V('Types', $Row);
      
      if ($Types && !in_array($Type, $Types)) {
//         echo "Skipping $LongCode\n";
         continue;
      }
      
      if (isset($Values[$LongCode])) {
         $Value = $Values[$LongCode];
         if (!$Value)
            $Value = TRUE;
      } elseif ($Short && isset($Values[$Short])) {
         $Value = $Values[$Short];
         if (!$Value)
            $Value = TRUE;
      } elseif (isset($Row['Default'])) {
         $Value = $Row['Default'];
      } else {
         $Value = NULL;
      }
      
      if (!$Value) {
         $Default = V('Default', $Row, NULL);
         if ($Default === NULL) {
            if ($Req) {
               $Errors[] = "Missing required parameter: $LongCode";
            }
            
            continue;
         } else {
            $Value = $Default;
         }
      }
      
      if ($AllowedValues = V('Values', $Row)) {
         if (!in_array($Value, $AllowedValues)) {
            $Errors[] = "Invalid value for parameter: $LongCode. Must be one of: ".implode(', ', $AllowedValues);
            continue;
         }
      }
      
      $Field = V('Field', $Row, $LongCode);
      $Result[$Field] = $Value;
   }
   
   if (count($Errors)) {
      echo implode("\n", $Errors)."\n";
      return FALSE;
   }

   
   return $Result;
}

function WriteCommandLineHelp($Options = NULL, $Section = '') {
   if ($Options === NULL) {
      $Options = GetAllCommandLineOptions(TRUE);
      foreach ($Options as $Section => $Options) {
         WriteCommandLineHelp($Options, $Section);
      }
      return;
   }
   
   echo "$Section\n\n";
   foreach ($Options as $Longname => $Options) {
      echo "  ";
      
      if (isset($Options['Short']))
         echo '-'.$Options['Short'].', ';
      
      echo "--$Longname";
      
      if (!V('Req', $Options))
         echo ' (Optional)';
      
      echo "\n    {$Options[0]}\n";
      
      if ($Values = V('Values', $Options)) {
         echo '    Valid Values: '.implode(', ', $Values)."\n";
      }
      
      echo "\n";
   }
}

function V($Name, $Array, $Default = NULL) {
   if (isset($Array[$Name]))
      return $Array[$Name];
   return $Default;
}
?>