<?php

/**
 *
 */

use Porter\ConnectionManager;
use Porter\FileTransfer;
use Porter\Migration;
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
 * Setup a new migration.
 *
 * @param string $inputName
 * @param string $outputName
 * @param string $sourcePrefix
 * @param string $targetPrefix
 * @param string|null $limitTables
 * @return Migration
 * @throws Exception
 */
function migrationFactory(
    string $inputName,
    string $outputName,
    string $sourcePrefix = '',
    string $targetPrefix = '',
    ?string $limitTables = ''
): Migration {
    // @todo Delete $inputDB after Sources are all moved to $inputStorage.
    $inputDB = new \Porter\Database\DbFactory((new ConnectionManager($inputName))->connection()->getPDO());
    $inputStorage = new Storage\Database(new ConnectionManager($inputName, $sourcePrefix));
    if ($outputName === 'file') { // @todo storageFactory
        $porterStorage = new Storage\File(); // Only 1 valid 'file' type currently.
        $outputStorage = new Storage\File(); // @todo dead variable (halts at porter step)
        $postscriptStorage = new Storage\File(); // @todo dead variable (halts at porter step)
    } else {
        $porterStorage = new Storage\Database(new ConnectionManager($outputName, 'PORT_'));
        $outputStorage = new Storage\Database(new ConnectionManager($outputName, $targetPrefix));
        $postscriptStorage = new Storage\Database(new ConnectionManager($outputName, $targetPrefix));
    }
    $captureOnly = ($outputName === 'sql');
    return new Migration(
        $inputDB,
        $inputStorage,
        $porterStorage,
        $outputStorage,
        $postscriptStorage,
        loadStructure(),
        $limitTables,
        $captureOnly
    );
}

/**
 * @param Source $source
 * @param Target $target
 * @param string $inputName
 * @param string $sourcePrefix
 * @return FileTransfer
 * @throws Exception
 */
function fileTransferFactory(Source $source, Target $target, string $inputName, string $sourcePrefix = ''): FileTransfer
{
    $inputStorage = new Storage\Database(new ConnectionManager($inputName, $sourcePrefix));
    return new FileTransfer($source, $target, $inputStorage);
}


/**
 * Build a valid path from multiple pieces.
 *
 * @param array|string $paths
 * @param  string $delimiter
 * @return string
 */
function combinePaths(array|string $paths, string $delimiter = '/'): string
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
 * Human-readable filesize output.
 *
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
 */
function generateThumbnail($path, $thumbPath, $height = 50, $width = 50): void
{
    list($widthSource, $heightSource, $type) = getimagesize($path);

    $xCoordinate = 0;
    $yCoordinate = 0;
    $heightDiff = $heightSource - $height;
    $widthDiff = $widthSource - $width;
    if ($widthDiff > $heightDiff) {
        // Crop the original width down
        $newWidthSource = round(($width * $heightSource) / $height);

        // And set the original x position to the cropped start point.
        $xCoordinate = round(($widthSource - $newWidthSource) / 2);
        $widthSource = $newWidthSource;
    } else {
        // Crop the original height down
        $newHeightSource = round(($height * $widthSource) / $width);

        // And set the original y position to the cropped start point.
        $yCoordinate = round(($heightSource - $newHeightSource) / 2);
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
            $xCoordinate,
            $yCoordinate,
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
        echo "Could not generate a thumbnail for " . $targetImage;
    }
}
