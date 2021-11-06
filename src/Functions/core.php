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
    if (array_key_exists($type, getSupportList())) {
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
 *
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
 *
 *
 * @param  $sql
 * @return array
 */
function parseSelect($sql)
{
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
 * @param  $matches
 * @return mixed
 */
function _replaceCommas($matches)
{
    return str_replace(',', '!COMMA!', $matches[0]);
}

/**
 *
 *
 * @param  $parsed
 * @return string
 */
function selectString($parsed)
{
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
