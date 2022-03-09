<?php

namespace Porter;

use Illuminate\Database\Capsule\Manager as Capsule;

class Connection
{
    /** @var array Valid values for $type. */
    public const ALLOWED_TYPES = ['database', 'files', 'api'];

    protected string $type = 'database';

    protected string $alias = '';

    protected array $info = [];

    public Capsule $dbm;

    /**
     * If no connect alias is give, initiate a test connection.
     *
     * @param string $alias
     */
    public function __construct(string $alias = '')
    {
        if (!empty($alias)) {
            $info = Config::getInstance()->getConnectionAlias($alias);
        } else {
            $info = Config::getInstance()->getTestConnection();
        }
        $this->setInfo($info);
        $this->setType($info['type']);

        // Set Illuminate Database instance.
        if ($info['type'] === 'database') {
            $capsule = new Capsule();
            $capsule->addConnection($this->translateConfig($info));
            $this->dbm = $capsule;
            //$capsule->setAsGlobal();
            //$capsule->bootEloquent();
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

    public function getInfo(string $name): array
    {
        return $this->info[$name];
    }

    /**
     * @return array
     */
    public function getAllInfo(): array
    {
        return $this->info;
    }

    /**
     * Get the database connection.
     */
    public function open()
    {
        $this->dbm->getConnection();
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
