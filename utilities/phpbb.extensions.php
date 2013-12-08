<?php
// Add file extension to hashed phpBB3 attachment filenames.
// Run this separately from the rest of Porter after editing the fields below.

// Add your database connection info here.
$DatabaseServer = 'localhost';
$DatabaseUser = 'root';
$DatabasePassword = 'password';
$DatabaseName = 'ynab';

// Absolute path to the folder you're importing.
$Directory = '/www/phpbb/files';

// No more editing!

// Select attachments
mysqli_connect($DatabaseServer, $DatabaseUser, $DatabasePassword);
mysqli_select_db($DatabaseName);
$Results = mysqli_query("select physical_filename as name, extension as ext from phpbb_attachments");

// Iterate thru files based on database results and rename.
$Renamed = $Failed = 0;
while ($Row = mysqli_fetch_array($Results)) {
   if (file_exists($Directory.$Row['name'])) {
      rename($Directory.$Row['name'], $Directory.$Row['physical_filename'].'.'.$Row['ext']);
      $Renamed++;
   }
   else {
      $Failed++;
   }
}

// Results
echo 'Renamed '.$Renamed.' files. '.$Failed. 'failures.';