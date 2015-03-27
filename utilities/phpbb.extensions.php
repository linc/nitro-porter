<?php
// Add file extension to hashed phpBB3 attachment filenames.
// Run this separately from the rest of Porter after editing the fields below.

// Add your database connection info here.
$DatabaseServer = 'localhost';
$DatabaseUser = '';
$DatabasePassword = '';
$DatabaseName = '';

// Absolute path to the folder you're importing, including trailing slash.
$Directory = '/path/to/attachments/';

// No more editing!

// Select attachments
$LinkIdentifier = mysqli_connect($DatabaseServer, $DatabaseUser, $DatabasePassword);
mysqli_select_db($LinkIdentifier, $DatabaseName);
$Results = mysqli_query(
    $LinkIdentifier,
    "select physical_filename as name, extension as ext from phpbb_attachments"
);

// Iterate thru files based on database results and rename.
$Renamed = $Failed = 0;
while ($Row = mysqli_fetch_array($Results, MYSQLI_ASSOC)) {
    if (file_exists($Directory . $Row['name'])) {
        rename($Directory . $Row['name'], $Directory . $Row['name'] . '.' . $Row['ext']);
        $Renamed++;
    } else {
        $Failed++;
    }
}

// Results
echo 'Renamed ' . $Renamed . ' files. ' . $Failed . 'failures.';
