<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

use Porter\Connection;
use Porter\Database\DbFactory;
use Porter\ExportModel;
use Porter\Request;
use Porter\Package;
use Porter\PackageSupport;

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
function loadManifest(): array
{
    return include(ROOT_DIR . '/data/manifest.php');
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
 * Get valid package class. Exit app if invalid package name is given.
 *
 * @param string $packageName
 * @return Package
 */
function packageFactory(string $packageName): Package
{
    if (!array_key_exists($packageName, PackageSupport::getInstance()->get())) {
        exit('Unsupported package: ' . $packageName);
    }

    $class = '\Porter\Package\\' . ucwords($packageName);
    return new $class();
}

/**
 * @param Request $request
 * @return ExportModel
 */
function modelFactory(Request $request, array $info): ExportModel
{
    // Wire old database / model mess.
    $db = new DbFactory($info, 'pdo');
    $model = new ExportModel($db);

    // Set model properties.
    $model->prefix = $request->get('src-prefix') ?? '';
    $model->destination = $request->get('dest') ?? 'file';
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
