<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

/**
 * Retreive settings from the config.
 */
function loadConfig()
{
    return include(ROOT_DIR . '/config.php');
}

function loadManifest()
{
    return include(ROOT_DIR . '/manifest.php');
}

/**
 * Retrieve test db creds from main config for Phinx.
 */
function getTestDatabaseCredentials()
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
 * @return \NitroPorter\ExportController
 */
function getValidPackage(string $packageName): \NitroPorter\ExportController
{
    if (!array_key_exists($packageName, \NitroPorter\SupportManager::getInstance()->getSupport())) {
        echo 'Unsupported package: ' . $packageName;
        exit();
    }

    $class = '\NitroPorter\Package\\' . ucwords($packageName);
    return new $class();
}

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
 * @deprecated
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
 * @deprecated
 * @param $name
 * @param $array
 * @param null $default
 * @return mixed|null
 */
function v($name, $array, $default = null)
{
    if (isset($array[$name])) {
        return $array[$name];
    }

    return $default;
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
