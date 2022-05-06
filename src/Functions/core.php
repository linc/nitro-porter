<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

use Porter\Connection;
use Porter\Database\DbFactory;
use Porter\ExportModel;
use Porter\ImportModel;
use Porter\Postscript;
use Porter\Request;
use Porter\Source;
use Porter\Target;
use Porter\Storage;

/**
 * Retrieve the config.
 *
 * @return array
 */
function loadConfig(): array
{
    return require(ROOT_DIR . '/config.php');
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
 * Get postscript class if it exists.
 *
 * @param string $target
 * @param Connection $connection
 * @return Postscript|null
 */
function postscriptFactory(string $target, Storage $storage, Connection $connection): ?Postscript
{
    $postscript = null;
    $class = '\Porter\Postscript\\' . ucwords($target);
    if (class_exists($class)) {
        $postscript = new $class($storage, $connection);
    }

    return $postscript;
}

/**
 * @param Request $request
 * @param Connection $sourceConnect
 * @param Storage $storage
 * @param Connection $targetConnect
 * @return ExportModel
 */
function exportModelFactory(
    Request    $request,
    Connection $sourceConnect,
    Storage    $storage,
    Connection $targetConnect
): ExportModel {
    // Wire old database / model mess.
    $info = $sourceConnect->getAllInfo();
    $db = new DbFactory($info, 'pdo');
    $map = loadStructure();
    $model = new ExportModel($db, $map, $storage, $targetConnect);

    // Set model properties.
    $model->srcPrefix = $request->get('src-prefix') ?? '';
    $model->testMode = $request->get('test') ?? false;
    $model->limitTables((string) $request->get('tables'));
    $model->captureOnly = $request->get('dumpsql') ?? false;

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
 * @param float $elapsed
 * @return string
 */
function formatElapsed(float $elapsed): string
{
    $m = floor($elapsed / 60);
    $s = $elapsed - $m * 60;
    return ($m) ? sprintf('%d:%05.2f', $m, $s) : sprintf('%05.2fs', $s);
}

/**
 * @param int $size
 * @return string
 */
function formatBytes(int $size): string
{
    $unit = ['b','kb','mb','gb','tb','pb'];
    return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 1) . $unit[$i];
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

    $targetImage = false;
    $sourceImage = false;
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
        if ($sourceImage) {
            imagedestroy($sourceImage);
        }

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
        if ($targetImage) {
            imagedestroy($targetImage);
        }
    } catch (\Exception $e) {
        echo "Could not generate a thumnail for " . $targetImage;
    }
}
