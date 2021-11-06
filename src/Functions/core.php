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
    $config = loadConfig();
    return $config['test_connections']['databases'][0]; // @todo
}

/**
 * Main export process.
 */
function dispatch($type)
{
    $method = 'DoExport';
    if (array_key_exists($type, \NitroPorter\SupportManager::getInstance()->getSupportList())) {
        $class = ucwords($type);
        $controller = new $class();
        if (!method_exists($controller, $method)) {
            echo "This datasource type does not support {$method}.\n";
            exit();
        }
        $controller->$method();
    } else {
        echo 'Invalid type specified: ' . htmlspecialchars($_POST['type']);
    }
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
