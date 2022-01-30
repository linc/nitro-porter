<?php

namespace Porter;

class Connection
{
    /** @var array Valid values for $type. */
    public const ALLOWED_TYPES = ['database', 'files', 'api'];

    protected string $type = 'database';

    protected string $alias = '';

    protected array $info = [];

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
}
