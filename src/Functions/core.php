<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

/**
 * Error handler.
 *
 * @param $errno
 * @param $errstr
 */
function errorHandler($errno, $errstr, $errFile, $errLine)
{
    $reportingLevel = error_reporting();

    // If error reporting is turned off, possibly by @.  Bail out.
    if (!$reportingLevel) {
        return;
    }

    if (defined('DEBUG') || ($errno != E_DEPRECATED && $errno != E_USER_DEPRECATED)) {
        $baseDir = realpath(__DIR__ . '/../') . '/';
        $errFile = str_replace($baseDir, null, $errFile);

        echo "Error in $errFile line $errLine: ($errno) $errstr\n";
        die();
    }
}

/**
 * Test filesystem permissions.
 */
function testWrite()
{
    // Create file
    $file = 'vanilla2test.txt';
    @touch($file);
    if (is_writable($file)) {
        @unlink($file);

        return true;
    } else {
        return false;
    }
}

/**
 *
 *
 * @param  $key
 * @param  null   $collection
 * @param  string $default
 * @return string
 */
function getValue($key, $collection = null, $default = '')
{
    if (!$collection) {
        $collection = $_POST;
    }
    if (array_key_exists($key, $collection)) {
        return $collection[$key];
    }

    return $default;
}

/**
 * Create a thumbnail from an image file.
 *
 * @param  $path
 * @param  $thumbPath
 * @param  int $height
 * @param  int $width
 * @return bool
 */
function generateThumbnail($path, $thumbPath, $height = 50, $width = 50)
{
    list($widthSource, $heightSource, $type) = getimagesize($path);

    $XCoord = 0;
    $YCoord = 0;
    $heightDiff = $heightSource - $height;
    $widthDiff = $widthSource - $width;
    if ($widthDiff > $heightDiff) {
        // Crop the original width down
        $newWidthSource = round(($width * $heightSource) / $height);

        // And set the original x position to the cropped start point.
        $XCoord = round(($widthSource - $newWidthSource) / 2);
        $widthSource = $newWidthSource;
    } else {
        // Crop the original height down
        $newHeightSource = round(($height * $widthSource) / $width);

        // And set the original y position to the cropped start point.
        $YCoord = round(($heightSource - $newHeightSource) / 2);
        $heightSource = $newHeightSource;
    }

    try {
        switch ($type) {
            case 1:
                $sourceImage = imagecreatefromgif($path);
                break;
            case 2:
                $sourceImage = @imagecreatefromjpeg($path);
                if (!$sourceImage) {
                    $sourceImage = imagecreatefromstring(file_get_contents($path));
                }
                break;
            case 3:
                $sourceImage = imagecreatefrompng($path);
                imagealphablending($sourceImage, true);
                break;
        }

        $targetImage = imagecreatetruecolor($width, $height);
        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            $XCoord,
            $YCoord,
            $width,
            $height,
            $widthSource,
            $heightSource
        );
        imagedestroy($sourceImage);

        switch ($type) {
            case 1:
                imagegif($targetImage, $thumbPath);
                break;
            case 2:
                imagejpeg($targetImage, $thumbPath);
                break;
            case 3:
                imagepng($targetImage, $thumbPath);
                break;
        }
        imagedestroy($targetImage);
    } catch (Exception $e) {
        echo "Could not generate a thumnail for " . $targetImage;
    }
}

/**
 *
 *
 * @param  $sql
 * @return array
 */
function parseSelect($sql)
{
    if (!preg_match('`^\s*select\s+(.+)\s+from\s+(.+)\s*`is', $sql, $matches)) {
        trigger_error("Could not parse '$sql'", E_USER_ERROR);
    }
    $result = array('Select' => array(), 'From' => '');
    $select = $matches[1];
    $from = $matches[2];

    // Replace commas within function calls.
    $select = preg_replace_callback('`\(([^)]+?)\)`', '_ReplaceCommas', $select);
    //   echo($select);
    $parts = explode(',', $select);

    $selects = array();
    foreach ($parts as $expr) {
        $expr = trim($expr);
        $expr = str_replace('!COMMA!', ',', $expr);

        // Check for the star match.
        if (preg_match('`(\w+)\.\*`', $expr, $matches)) {
            $result['Star'] = $matches[1];
        }

        // Check for an alias.
        if (preg_match('`^(.*)\sas\s(.*)$`is', $expr, $matches)) {
            //         decho($matches, 'as');
            $alias = trim($matches[2], '`');
            $selects[$alias] = $matches[1];
        } elseif (preg_match('`^[a-z_]?[a-z0-9_]*$`i', $expr)) {
            // We are just selecting one column.
            $selects[$expr] = $expr;
        } elseif (preg_match('`^[a-z_]?[a-z0-9_]*\.([a-z_]?[a-z0-9_]*)$`i', $expr, $matches)) {
            // We are looking at an alias'd select.
            $alias = $matches[1];
            $selects[$alias] = $expr;
        } else {
            $selects[] = $expr;
        }
    }

    $result['Select'] = $selects;
    $result['From'] = $from;
    $result['Source'] = $sql;

    return $result;
}

/**
 * Replace commas with a temporary placeholder.
 *
 * @param  $matches
 * @return mixed
 */
function _replaceCommas($matches)
{
    return str_replace(',', '!COMMA!', $matches[0]);
}

/**
 *
 *
 * @param  $parsed
 * @return string
 */
function selectString($parsed)
{
    // Build the select.
    $parts = $parsed['Select'];
    $selects = array();
    foreach ($parts as $alias => $expr) {
        if (is_numeric($alias) || $alias == $expr) {
            $selects[] = $expr;
        } else {
            $selects[] = "$expr as `$alias`";
        }
    }
    $select = implode(",\n  ", $selects);

    $from = $parsed['From'];

    $result = "select\n  $select\nfrom $from";

    return $result;
}

/**
 *
 *
 * @param  $paths
 * @param  string $delimiter
 * @return mixed
 */
function combinePaths($paths, $delimiter = '/')
{
    if (is_array($paths)) {
        $mungedPath = implode($delimiter, $paths);
        $mungedPath = str_replace(
            array($delimiter . $delimiter . $delimiter, $delimiter . $delimiter),
            array($delimiter, $delimiter),
            $mungedPath
        );

        return str_replace(array('http:/', 'https:/'), array('http://', 'https://'), $mungedPath);
    } else {
        return $paths;
    }
}

/**
 * Take the template package, add our new name, and make a new package from it.
 *
 * @param string $name
 */
function spawnPackage($name)
{

    if ($name && strlen($name) > 2) {
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name);
        $template = file_get_contents(__DIR__ . '/../tpl_package.txt');
        file_put_contents(__DIR__ . '/../Package/' . $name . '.php', str_replace('__NAME__', $name, $template));
        echo "Created new package: " . $name . "\n";
    } else {
        echo "Invalid name: 2+ alphanumeric characters only.";
    }
}

/**
 * Used to increase php max_execution_time value.
 *
 * @param  int $maxExecutionTime PHP max execution time in seconds.
 * @return bool Returns true if max_execution_time was increased (or stayed the same) or false otherwise.
 */
function increaseMaxExecutionTime($maxExecutionTime)
{
    $iniMaxExecutionTime = ini_get('max_execution_time');
    // max_execution_time == 0 means no limit.
    if ($iniMaxExecutionTime === '0') {
        return true;
    }
    if (((string)$maxExecutionTime) === '0') {
        return set_time_limit(0);
    }
    if (!ctype_digit($iniMaxExecutionTime) || $iniMaxExecutionTime < $maxExecutionTime) {
        return set_time_limit($maxExecutionTime);
    }
    return true;
}
