<?php

/**
 * Creating desired db instances on the go
 * Class DbFactory
 */
class DbFactory {

    /**
     * DB connection info
     * @var array
     */
    protected $dbInfo;

    /**
     * DbFactory constructor.
     * @param array $args
     */
    public function __construct(array $args) {
        $this->dbInfo = $args;
    }

    /**
     * Returns a db instance
     * @return db instance
     */
    public function getInstance() {
        $className = DB_TYPE . 'Db';
        return new $className($this->dbInfo);
    }
}

// Closing PHP tag required. (make.php)
?>

