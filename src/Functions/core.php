<?php

/**
 *
 */

use Porter\ConnectionManager;
use Porter\Database\DbFactory;
use Porter\ExportModel;
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
    if (file_exists(ROOT_DIR . '/config.php')) {
        return require(ROOT_DIR . '/config.php');
    } else {
        //trigger_error('Missing config.php — Make a copy of config-sample.php!');
        return require(ROOT_DIR . '/config-sample.php');
    }
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

function runPorter(Request $request): void
{
    (new \Porter\Controller())->run($request);
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
 * @return ?Target
 */
function targetFactory(string $target): ?Target
{
    if ('file' === $target) {
        return null;
    }

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
 * @param Storage $outputStorage
 * @param Storage $postscriptStorage
 * @return Postscript|null
 */
function postscriptFactory(string $target, Storage $outputStorage, Storage $postscriptStorage): ?Postscript
{
    $postscript = null;
    $class = '\Porter\Postscript\\' . ucwords($target);
    if (class_exists($class)) {
        $postscript = new $class($outputStorage, $postscriptStorage);
    }

    return $postscript;
}

/**
 * @param DbFactory $inputDB
 * @param Storage $inputStorage
 * @param Storage $porterStorage
 * @param Storage $outputStorage
 * @param string $sourcePrefix
 * @param string|null $limitTables
 * @param bool $captureOnly
 * @return ExportModel
 */
function exportModelFactory(
    DbFactory $inputDB, // @todo remove
    Storage $inputStorage,
    Storage $porterStorage,
    Storage $outputStorage,
    Storage $postscriptStorage,
    string $sourcePrefix = '',
    ?string $limitTables = '',
    bool $captureOnly = false
): ExportModel {
    return new ExportModel(
        $inputDB,
        $inputStorage,
        $porterStorage,
        $outputStorage,
        $postscriptStorage,
        loadStructure(),
        $sourcePrefix,
        $limitTables,
        $captureOnly
    );
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
    if (!$size) {
        return '0b';
    }
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
