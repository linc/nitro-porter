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

    /** @var Connection Current connection being used. */
    protected Connection $connection;

    public Capsule $dbm;

    /**
     * If no connect alias is give, initiate a test connection.
     *
     * @param string $alias
     */
    public function __construct(string $alias = '')
    {
        if (!empty($alias)) {
            $this->alias = $alias;
            $info = Config::getInstance()->getConnectionAlias($alias);
        } else {
            $info = Config::getInstance()->getTestConnection();
        }

        if (!isset($info['type'])) {
            trigger_error('Config error: Connection `type` not found for  "' . $alias . '"', E_USER_ERROR);
        }

        $this->setInfo($info);
        $this->setType($info['type']);

        // Set Illuminate Database instance.
        if ($info['type'] === 'database') {
            $capsule = new Capsule();
            $capsule->addConnection($this->translateConfig($info), $info['alias']);
            $this->dbm = $capsule;
            $this->newConnection();
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
    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * Get a new DBM connection.
     *
     * @return Connection
     */
    public function newConnection(): Connection
    {
        $this->connection = $this->dbm->getConnection($this->alias);

        if ($this->connection->getDriverName() === 'mysql') {
            $this->optimizeMySQL();
        }

        return $this->connection;
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
     */
    protected function optimizeMySQL(): void
    {
        // Always disable data integrity checks.
        $this->connection->unprepared("SET foreign_key_checks = 0");

        // Set the timezone to UTC. Avoid named timezones because they may not be loaded.
        $this->connection->unprepared("SET time_zone = '+00:00'");

        // Log all queries if debug mode is enabled.
        if (\Porter\Config::getInstance()->debugEnabled()) {
            // See ${hostname}.log in datadir (find with `SHOW GLOBAL VARIABLES LIKE 'datadir'`)
            $this->connection->unprepared("SET GLOBAL general_log = 1");
        }
    }
}
