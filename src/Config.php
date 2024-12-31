<?php

namespace Porter;

use http\Exception;

class Config
{
    private static $instance = null;

    protected array $config = [
        'debug' => false,
        'test_alias' => 'test',
        'connections' => [],
    ];

    /**
     * Make it a singleton; there's only 1 config.
     */
    public static function getInstance(): self
    {
        if (self::$instance == null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    public function set(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get all connections available.
     *
     * @return array
     */
    public function getConnections(): array
    {
        return $this->config['connections'];
    }

    /**
     * Whether debug mode is enabled in the config.
     *
     * @return bool
     */
    public function debugEnabled(): bool
    {
        return $this->config['debug'];
    }

    /**
     * Get designated test connection.
     *
     * @return array
     */
    public function getTestConnection(): array
    {
        if (!isset($this->config['test_alias'])) {
            trigger_error('Config must include `test_alias` key to run tests.');
        }
        return $this->getConnectionAlias($this->config['test_alias']);
    }

    /**
     * Get config data for a connection by its alias.
     *
     * @param string $alias
     * @return array
     */
    public function getConnectionAlias(string $alias): array
    {
        $result = [];
        foreach ($this->config['connections'] as $connection) {
            if ($alias === $connection['alias']) {
                $result = $connection;
                break;
            }
        }

        $this->validateConnectionInfo($alias, $result);

        return $result;
    }

    /**
     * Validate config has required info.
     *
     * @param string $alias
     * @param array $info
     * @throws \Exception
     */
    protected function validateConnectionInfo(string $alias, array $info): void
    {
        // Alias not in config.
        if (empty($info)) {
            //trigger_error('Config error: Alias "' . $alias . '" not found', E_USER_ERROR);
            throw new \Exception('Alias "' . $alias . '" not found in config');
        }

        // Type is required.
        if (empty($info['type'])) {
            throw new \Exception('No connection `type` for alias "' . $alias . '" in config');
        }

        // Database required fields.
        if ($info['type'] === 'database') {
            foreach (['adapter', 'host','name','user'] as $property) {
                if (!array_key_exists($property, $info)) {
                    throw new \Exception('Database `' . $property . '` missing for alias "' . $alias . '" in config');
                }
            }
        }
    }
}
