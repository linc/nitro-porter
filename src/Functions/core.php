<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

/**
 * Retrieve settings from the config.
 */
function loadConfig(): array
{
    return include(ROOT_DIR . '/config.php');
}

function loadManifest(): array
{
    return include(ROOT_DIR . '/manifest.php');
}

/**
 * Retrieve test db creds from main config for Phinx.
 */
function getTestDatabaseCredentials(): array
{
    return loadConfig()['test_connections']['databases'][0]; // @todo
}

/**
 * Get a cleaned up database source list for forms.
 *
 * @return array A list of connections in the config (id => name).
 */
function getSourceConnections(): array
{
    $prepared_connections = [];
    foreach (loadConfig()['connections']['databases'] as $c) {
        $prepared_connections[$c['alias']] = $c['alias'] . ' (' . $c['user'] . '@' . $c['name'] . ')';
    }
    return $prepared_connections;
}

/**
 * Get valid package class. Exit app if invalid package name is given.
 *
 * @param string $packageName
 * @return \Porter\ExportController
 */
function getValidPackage(string $packageName): \Porter\ExportController
{
    if (!array_key_exists($packageName, \Porter\PackageSupport::getInstance()->get())) {
        echo 'Unsupported package: ' . $packageName;
        exit();
    }

    $class = '\Porter\Package\\' . ucwords($packageName);
    return new $class();
}

/**
 * Provides a chained callable for the router.
 *
 * @param \Porter\Request $request
 */
function buildAndRun(\Porter\Request $request): void
{
    \Porter\ExportFactory::build()->run($request);
}

/**
 * Error handler.
 *
 * @param $errno
 * @param $errstr
 * @param $errFile
 * @param $errLine
 */
function errorHandler($errno, $errstr, $errFile, $errLine): void
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
 * @return string
 */
function getValue(string $key, array $collection = [], string $default = ''): string
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
function v(string $name, array $array, $default = ''): string
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
