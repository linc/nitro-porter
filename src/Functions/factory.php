<?php

/**
 * Porter factories.
 */

use Porter\ConnectionManager;
use Porter\FileTransfer;
use Porter\Migration;
use Porter\Postscript;
use Porter\Source;
use Porter\Target;
use Porter\Storage;

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
