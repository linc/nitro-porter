<?php

$GlobalOptions = array(
   // Used shortcodes: t, n, u, p, h, x, a, c, f, d, o, s
   'type'      => array('Type of forum we\'re freeing you from.', 'Req' => TRUE, 'Sx' => ':', 'Field' => 'type', 'Short' => 't'),
   'dbname'    => array('Database name.', 'Req' => TRUE, 'Sx' => ':', 'Field' => 'dbname', 'Short' => 'n'),
   'user'      => array('Database connection username.', 'Req' => TRUE, 'Sx' => ':', 'Field' => 'dbuser', 'Short' => 'u'),
   'password'  => array('Database connection password.', 'Sx' => '::', 'Field' => 'dbpass', 'Short' => 'p', 'Default' => ''),
   'host'      => array('IP address or hostname to connect to. Default is 127.0.0.1.', 'Sx' => ':', 'Field' => 'dbhost', 'Short' => 'o', 'Default' => '127.0.0.1'),
   'prefix'    => array('The table prefix in the database.', 'Field' => 'prefix', 'Sx' => ':', 'Default' => '', 'Short' => 'x'),
   'avatars'   => array('Enables exporting avatars from the database if supported.', 'Sx' => '::', 'Field' => 'avatars', 'Short' => 'a', 'Default' => ''),
   'cdn'       => array('Prefix to be applied to file paths.', 'Field' => 'cdn', 'Sx' => ':', 'Short' => 'c', 'Default' => ''),
   'files'     => array('Enables exporting attachments from database if supported.', 'Sx' => '::', 'Short' => 'f', 'Default' => ''),
   'destpath'  => array('Define destination path for the export file.', 'Sx' => '::', 'Short' => 'd', 'Default' => ''),
   'spawn'     => array('Create a new package with this name.', 'Sx' => '::', 'Short' => 's', 'Default' => ''),
   'help'      => array('Show this help, duh.', 'Short' => 'h')
);

// Go through all of the supported types and add them to the type description.
if (isset($Supported))
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

function parseCommandLine($Options = NULL, $Files = NULL) {
   global $GlobalOptions, $Supported, $argv;
   
   if (isset($Options))
      $GlobalOptions = $Options;
   if (!isset($GlobalOptions))
      $GlobalOptions = array();
   if (!isset($Supported))
      $Supported = array();
   
   $CommandOptions = GetAllCommandLineOptions();
   list($ShortCodes, $LongCodes) = GetOptCodes($CommandOptions);
   
//   print_r($LongCodes);
   
   $Opts = getopt($ShortCodes, $LongCodes);
   
   if (isset($Opts['help']) || isset($Opts['h'])) {
      WriteCommandLineHelp();
      die();
   }

   // Spawn new packages from the command line!
   if (isset($Opts['spawn']) || isset($Opts['s'])) {
      $Name = (isset($Opts['spawn'])) ? $Opts['spawn'] : $Opts['s'];
      SpawnPackage($Name);
      die();
   }
   
   $Opts = ValidateCommandLine($Opts, $CommandOptions);
   
   if (is_array($Files)) {
      $Opts2 = array();
      foreach ($Files as $Name) {
         $Value = array_pop($argv);
         if (!$Value) {
            echo "Missing required parameter: $Name";
         } else {
            $Opts2[$Name] = $Value;
         }
      }
      if ($Opts2) {
         if ($Opts === FALSE)
            $Opts = $Opts2;
         else
            $Opts = array_merge($Opts, $Opts2);
      }
   }
   
   if ($Opts === FALSE) {
      die();
   }
   
   $_POST = $Opts;
   return $Opts;
}

function validateCommandLine($Values, $Options) {
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

function writeCommandLineHelp($Options = NULL, $Section = '') {
   if ($Options === NULL) {
      $Options = GetAllCommandLineOptions(TRUE);
      foreach ($Options as $Section => $Options) {
         WriteCommandLineHelp($Options, $Section);
      }
      return;
   }
   
   echo "$Section\n";
   foreach ($Options as $Longname => $Options) {
      $Output = "  ";
      
      if (isset($Options['Short']))
         $Output .= '-'.$Options['Short'].', ';
      
      $Output .= "--$Longname";

      // Align our descriptions by passing
      $Output = str_pad($Output, 18, ' ');

      if (V('Req', $Options))
         $Output .= 'Required. ';

      $Output .= "{$Options[0]}\n";
      
      if ($Values = V('Values', $Options)) {
         $Output .= '    Valid Values: '.implode(', ', $Values)."\n";
      }
      
      echo $Output;
   }

   echo "\n";
}

function V($Name, $Array, $Default = NULL) {
   if (isset($Array[$Name]))
      return $Array[$Name];
   return $Default;
}
?>