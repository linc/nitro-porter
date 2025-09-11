<?php

namespace Porter\Database;

use PDO;

/**
 * Creating desired db instances on the go
 * @deprecated
 */
class DbFactory
{
    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * DbFactory constructor.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Returns a db instance
     *
     * @return object db instance
     */
    public function getInstance()
    {
        $className = '\Porter\Database\\PdoDB';
        return new $className($this->pdo);
    }
}
