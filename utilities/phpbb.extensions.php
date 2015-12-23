<?php
// Add file extension to hashed phpBB3 attachment filenames.
// Run this separately from the rest of Porter after editing the fields below.

// Add your database connection info here.
$databaseServer = 'localhost';
$databaseUser = '';
$databasePassword = '';
$databaseName = '';

// Absolute path to the folder you're importing, including trailing slash.
$directory = '/path/to/attachments/';

// No more editing!

// Select attachments
$linkIdentifier = mysqli_connect($databaseServer, $databaseUser, $databasePassword);
mysqli_select_db($linkIdentifier, $databaseName);
$results = mysqli_query(
    $linkIdentifier,
    "select physical_filename as name, extension as ext from phpbb_attachments"
);

// Iterate thru files based on database results and rename.
$renamed = $failed = 0;
while ($row = mysqli_fetch_array($results, MYSQLI_ASSOC)) {
    if (file_exists($directory . $row['name'])) {
        rename($directory . $row['name'], $directory . $row['name'] . '.' . $row['ext']);
        $renamed++;

        if (file_exists($directory . 'thumb_' . $row['name'])) {
            rename($directory . 'thumb_' . $row['name'], $directory . 'thumb_' . $row['name'] . '.' . $row['ext']);
        }
    } else {
        $failed++;
    }
}

// Results
echo 'Renamed ' . $renamed . ' files. ' . $failed . 'failures.';
