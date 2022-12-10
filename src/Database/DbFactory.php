<?php

namespace Porter\Database;

/**
 * Creating desired db instances on the go
 * @deprecated
 */
class DbFactory
{
    /**
     * @var array DB connection info
     */
    private $dbInfo;

    /**
     * @var string php database extension
     */
    private $extension;

    /**
     * DbFactory constructor.
     *
     * @param array  $args      db connection parameters
     * @param string $extension db extension
     */
    public function __construct(array $args, $extension)
    {
        $this->dbInfo = $args;
        $this->extension = $extension;
    }

    /**
     * Returns a db instance
     *
     * @return object db instance
     */
    public function getInstance()
    {
        $className = '\Porter\Database\\' . ucwords($this->extension) . 'DB';
        if (!class_exists($className)) {
            trigger_error($this->extension . ' extension not found.');
        }

        $dbFactory = new $className($this->dbInfo);
        if (!($dbFactory instanceof DbResource)) {
            trigger_error($className . 'does not implement DbResource.');
        }

        return $dbFactory;
    }
}
