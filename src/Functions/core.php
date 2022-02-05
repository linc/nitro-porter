<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

use Porter\Connection;
use Porter\Database\DbFactory;
use Porter\ExportModel;
use Porter\ImportModel;
use Porter\Request;
use Porter\Source;
use Porter\Target;
use Porter\Support;

/**
 * Retrieve the config.
 *
 * @return array
 */
function loadConfig(): array
{
    return include(ROOT_DIR . '/config.php');
}

/**
 * @return array
 */
function loadSources(): array
{
    return include(ROOT_DIR . '/data/sources.php');
}

/**
 * @return array
 */
function loadTargets(): array
{
    return include(ROOT_DIR . '/data/targets.php');
}

/**
 * @return array
 */
function loadStructure(): array
{
    return include(ROOT_DIR . '/data/structure.php');
}

/**
 * Retrieve test db creds from main config for Phinx.
 *
 * @return array
 */
function getTestDatabaseCredentials(): array
{
    $c = new \Porter\Connection();
    return $c->getAllInfo();
}

/**
 * Get valid source class. Exit app if invalid name is given.
 *
 * @param string $source
 * @return Source
 */
function sourceFactory(string $source): Source
{
    $class = '\Porter\Source\\' . ucwords($source);
    if (!class_exists($class)) {
        exit('Unsupported source: ' . $source);
    }

    return new $class();
}

/**
 * Get valid target class. Exit app if invalid name is given.
 *
 * @param string $target
 * @return Target
 */
function targetFactory(string $target): Target
{
    $class = '\Porter\Target\\' . ucwords($target);
    if (!class_exists($class)) {
        exit('Unsupported target: ' . $target);
    }

    return new $class();
}

/**
 * @return ImportModel
 */
function importModelFactory(): ImportModel
{
    return new ImportModel();
}

/**
 * @param Request $request
 * @return ExportModel
 */
function exportModelFactory(Request $request, array $info): ExportModel
{
    // Wire old database / model mess.
    $db = new DbFactory($info, 'pdo');
    $map = loadStructure();
    $model = new ExportModel($db, $map);

    // Set model properties.
    $model->prefix = $request->get('src-prefix') ?? '';
    $model->destDb = $request->get('destdb');
    $model->testMode = $request->get('test') ?? false;
    $model->loadTables((string) $request->get('tables'));

    return $model;
}

/**
 * Error handler.
 *
 * @param int $level
 * @param string $msg
 * @param string $file
 * @param int $line
 * @param array $context
 */
function errorHandler(int $level, string $msg, string $file, int $line, array $context = []): void
{
    $reportingLevel = error_reporting();
    if (!$reportingLevel) {
        return; // Error reporting is off or suppressed.
    }

    if (defined('DEBUG') || ($level !== E_DEPRECATED && $level !== E_USER_DEPRECATED)) {
        $baseDir = realpath(__DIR__ . '/../') . '/';
        $errFile = str_replace($baseDir, null, $file);
        echo "Error in $errFile line $line: ($level) $msg\n";
        die();
    }
}

/**
 * Test filesystem permissions.
 */
function testWrite(): bool
{
    $file = 'portertest.txt';
    @touch($file);
    if (is_writable($file)) {
        @unlink($file);
        return true;
    } else {
        return false;
    }
}

/**
 * @deprecated
 * @param  string $key
 * @param  array $collection
 * @param  string $default
 * @return mixed
 */
function getValue(string $key, array $collection = [], string $default = '')
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
 * @deprecated
 * @param string $name
 * @param array $array
 * @param string $default
 * @return mixed|null
 */
function v(string $name, array $array, $default = '')
{
    if (isset($array[$name])) {
        return $array[$name];
    }

    return $default;
}

/**
 *
 *
 * @param  array|string $paths
 * @param  string $delimiter
 * @return string
 */
function combinePaths($paths, string $delimiter = '/'): string
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
 * For outputting how long the export took.
 *
 * @param  int $start
 * @param  int $end
 * @return string
 */
function formatElapsed($start, $end = null)
{
    if ($end === null) {
        $elapsed = $start;
    } else {
        $elapsed = $end - $start;
    }

    $m = floor($elapsed / 60);
    $s = $elapsed - $m * 60;
    $result = sprintf('%02d:%05.2f', $m, $s);

    return $result;
}

/**
 * Create a thumbnail from an image file.
 *
 * @param string $path
 * @param string $thumbPath
 * @param  int $height
 * @param  int $width
 * @return void
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
    } catch (\Exception $e) {
        echo "Could not generate a thumnail for " . $targetImage;
    }
}
