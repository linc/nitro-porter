<?php

namespace NitroPorter;

use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    /**
     * Database constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $capsule = new Capsule();
        $capsule->addConnection($this->translateConfig($config));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    /**
     * Map keys from our config to Illuminate's.
     * @param array $config
     * @return array
     */
    public function translateConfig(array $config): array
    {
        // Valid keys: driver, host, database, username, password, charset, collation, prefix
        return $config;
    }
}
