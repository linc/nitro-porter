<?php

/**
 *
 */

namespace Porter;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;

/**
 * Monolog wrapper
 * @see http://seldaek.github.io/monolog/doc/01-usage.html
 */
class Log
{
    public static $logger = null;

    /**
     * Only need one logger for now.
     *
     * @return Logger
     */
    public static function getInstance(): Logger
    {
        if (self::$logger !== null) {
            return self::$logger;
        }

        // Create the logger
        self::$logger = new Logger('porter');

        // Add handlers
        self::$logger->pushHandler(new StreamHandler(__DIR__ . '/../porter.log', Logger::DEBUG));
        self::$logger->pushHandler(new FirePHPHandler());

        return self::$logger;
    }

    /**
     * Temporary 1:1 replacement for old inline logger.
     *
     * @param string $message
     */
    public static function comment(string $message)
    {
        self::getInstance()->info($message);
    }
}
