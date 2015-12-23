<?php
/**
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Error handler.
 *
 * @param $errno
 * @param $errstr
 */
function errorHandler($errno, $errstr, $errFile, $errLine) {
    $reportingLevel = error_reporting();

    // If error reporting is turned off, possibly by @.  Bail out.
    if (!$reportingLevel) {
        return;
    }

    if (defined(DEBUG) || ($errno != E_DEPRECATED && $errno != E_USER_DEPRECATED)) {
        $baseDir = realpath(__DIR__.'/../').'/';
        $errFile = str_replace($baseDir, null, $errFile);

        echo "Error in $errFile line $errLine: ($errno) $errstr\n";
        die();
    }
}

/**
 * Debug echo tool.
 *
 * @param $var
 * @param string $prefix
 */
function decho($var, $prefix = 'debug') {
    echo '<pre><b>' . $prefix . '</b>: ' . htmlspecialchars(print_r($var, true)) . '</pre>';
}

/**
 * Write out a value passed as bytes to its most readable format.
 */
function formatMemorySize($bytes, $precision = 1) {
    $units = array('B', 'K', 'M', 'G', 'T');

    $bytes = max((int)$bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    $result = round($bytes, $precision) . $units[$pow];

    return $result;
}

/**
 * Test filesystem permissions.
 */
function testWrite() {
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
 * @param $key
 * @param null $collection
 * @param string $default
 * @return string
 */
function getValue($key, $collection = null, $default = '') {
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
 * @param $path
 * @param $thumbPath
 * @param int $height
 * @param int $width
 * @return bool
 */
function generateThumbnail($path, $thumbPath, $height = 50, $width = 50) {
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
                $sourceImage = imagecreatefromjpeg($path);
                break;
            case 3:
                $sourceImage = imagecreatefrompng($path);
                imagealphablending($sourceImage, true);
                break;
        }

        $targetImage = imagecreatetruecolor($width, $height);
        imagecopyresampled($targetImage, $sourceImage, 0, 0, $XCoord, $YCoord, $width, $height, $widthSource,
            $heightSource);
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
 * @param $sql
 * @return array
 */
function parseSelect($sql) {
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
 * @param $matches
 * @return mixed
 */
function _replaceCommas($matches) {
    return str_replace(',', '!COMMA!', $matches[0]);
}

/**
 *
 * @param type $sql
 * @param array $columns An array in the form Alias => Column or just Column
 * @return type
 */
function replaceSelect($sql, $columns) {
    if (is_string($sql)) {
        $parsed = parseSelect($sql);
    } else {
        $parsed = $sql;
    }

    // Set a prefix for new selects.
    if (isset($parsed['Star'])) {
        $px = $parsed['Star'] . '.';
    } else {
        $px = '';
    }

    $select = $parsed['Select'];

    $newSelect = array();
    foreach ($columns as $index => $value) {
        if (is_numeric($index)) {
            $alias = $value;
        } else {
            $alias = $index;
        }

        if (isset($select[$value])) {
            $newSelect[$alias] = $select[$value];
        } else {
            $newSelect[$alias] = $px . $value;
        }
    }
    $parsed['Select'] = $newSelect;

    if (is_string($sql)) {
        return selectString($parsed);
    } else {
        return $parsed;
    }
}

/**
 *
 *
 * @param $parsed
 * @return string
 */
function selectString($parsed) {
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
 * @param $paths
 * @param string $delimiter
 * @return mixed
 */
function combinePaths($paths, $delimiter = '/') {
    if (is_array($paths)) {
        $mungedPath = implode($delimiter, $paths);
        $mungedPath = str_replace(array($delimiter . $delimiter . $delimiter, $delimiter . $delimiter),
            array($delimiter, $delimiter), $mungedPath);

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
function spawnPackage($name) {

    if ($name && strlen($name) > 2) {
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name);
        $template = file_get_contents(__DIR__ . '/../tpl_package.txt');
        file_put_contents(__DIR__ . '/../packages/' . $name . '.php', str_replace('__NAME__', $name, $template));
        echo "Created new package: " . $name . "\n";
    } else {
        echo "Invalid name: 2+ alphanumeric characters only.";
    }
}

?>
