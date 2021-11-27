<?php

namespace NitroPorter;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Boot the database for global access.
 * @param array $config
 */
function bootDatabase(array $config)
{
    $capsule = new Capsule();
    $capsule->addConnection(translateConfig($config));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
}

/**
 * Map keys from our config to Illuminate's.
 * @param array $config
 * @return array
 */
function translateConfig(array $config): array
{
    // Valid keys: driver, host, database, username, password, charset, collation, prefix
    return $config;
}
