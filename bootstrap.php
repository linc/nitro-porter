<?php

// Autoload.
if (isset($GLOBALS['_composer_autoload_path'])) {
    // If running via Composer, use provided location.
    require_once $GLOBALS['_composer_autoload_path'];
} else {
    // If running locally, guess the location.
    foreach (['../..', '../vendor', 'vendor'] as $path) {
        $autoloader = __DIR__ . '/' . $path . '/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
            unset($autoloader);
            break;
        }
    }
}

// Environment.
const ROOT_DIR = __DIR__;
//set_error_handler("ErrorHandler");
if (ini_get('date.timezone') == '') {
    date_default_timezone_set('America/Detroit');
}

// Require & load config.
\Porter\Config::getInstance()->set(loadConfig());

// Load source & target support.
\Porter\Support::getInstance()->setSources(loadSources());
\Porter\Support::getInstance()->setTargets(loadTargets());
