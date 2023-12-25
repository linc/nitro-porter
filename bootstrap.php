<?php

// Autoload.
require_once __DIR__ . '/vendor/autoload.php';

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

// CLI app setup.
$app = new Ahc\Cli\Application('Nitro Porter', 'v4.0');

// Add commands.
$app->add(new Porter\Command\ListCommand());
$app->add(new Porter\Command\ShowCommand());
$app->add(new Porter\Command\RunCommand());

// Execute command.
$app->handle($_SERVER['argv']);
