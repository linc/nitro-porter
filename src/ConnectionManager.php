<?php

namespace Porter;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;

/**
 * Manages a single connection to a data source or target, like a database or API.
 */
class ConnectionManager
{
    /** @var array Valid values for $type. */
    public const ALLOWED_TYPES = ['database', 'files', 'api'];

    protected string $type = 'database';

    protected string $alias = '';

    protected array $info = [];

    /** @var Connection Connection used for reads. */
    protected Connection $readConnection;

    /** @var Connection Connection used for writes. */
    protected Connection $writeConnection;

    public Capsule $dbm;

    /**
     * If no connect alias is give, initiate a test connection.
     *
     * @param string $alias
     * @return array Connection info.
     */
    public function __construct(string $alias = '')
    {
        if (!empty($alias)) {
            $info = Config::getInstance()->getConnectionAlias($alias);
            $this->alias = $alias; // Provided alias.
        } else {
            $info = Config::getInstance()->getTestConnection();
            $this->alias = $info['alias']; // Test alias from config.
        }

        $this->setInfo($info);
        $this->setType($info['type']);

        // Set Illuminate Database instance.
        if ($info['type'] === 'database') {
            $capsule = new Capsule();
            $capsule->addConnection($this->translateConfig($info), $info['alias']);
            $this->dbm = $capsule;
            // Separate read/write connections for unbuffered queries.
            $this->readConnection = $this->newConnection();
            $this->writeConnection = $this->newConnection();
        }
    }

    public function setType(string $type)
    {
        if (in_array($type, self::ALLOWED_TYPES)) {
            $this->type = $type;
        }
    }

    public function setInfo(array $info)
    {
        $this->info = $info;
    }

    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * @return array
     */
    public function getAllInfo(): array
    {
        return $this->info;
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * Get the current DBM connection.
     *
     * @return Connection
     */
    public function readConnection(): Connection
    {
        return $this->readConnection;
    }

    /**
     * Get the current DBM connection.
     *
     * @return Connection
     */
    public function writeConnection(): Connection
    {
        return $this->writeConnection;
    }

    /**
     * Get a new DBM connection.
     *
     * @return Connection
     */
    public function newConnection(): Connection
    {
        $connection = $this->dbm->getConnection($this->alias);

        if ($connection->getDriverName() === 'mysql') {
            $this->optimizeMySQL($connection);
        }

        return $connection;
    }

    /**
     * Map keys from our config to Illuminate's.
     * @param array $config
     * @return array
     */
    public function translateConfig(array $config): array
    {
        // Valid keys: driver, host, database, username, password, charset, collation, prefix
        $config['driver'] = $config['adapter'];
        $config['database'] = $config['name'];
        $config['username'] = $config['user'];
        $config['password'] = $config['pass'];
        //$config['strict'] = false;

        return $config;
    }

    /**
     * Perform MySQL-specific connection optimizations.
     *
     * @param Connection $connection
     * @return Connection
     */
    protected function optimizeMySQL(Connection $connection): Connection
    {
        // Always disable data integrity checks.
        $connection->unprepared("SET foreign_key_checks = 0");

        // Set the timezone to UTC. Avoid named timezones because they may not be loaded.
        $connection->unprepared("SET time_zone = '+00:00'");

        // Log all queries if debug mode is enabled.
        if (\Porter\Config::getInstance()->debugEnabled()) {
            // See ${hostname}.log in datadir (find with `SHOW GLOBAL VARIABLES LIKE 'datadir'`)
            $connection->unprepared("SET GLOBAL general_log = 1");
        }

        return $connection;
    }
}
