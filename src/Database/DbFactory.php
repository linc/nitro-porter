<?php
namespace NitroPorter;

/**
 * Creating desired db instances on the go
 * Class DbFactory
 */
class DbFactory {

    /** @var array DB connection info */
    private $dbInfo;

    /** @var string php database extension */
    private $extension;

    /**
     * DbFactory constructor.
     *
     * @param array $args db connection parameters
     * @param string $extension db extension
     */
    public function __construct(array $args, $extension) {
        $this->dbInfo = $args;
        $this->extension = $extension;
    }

    /**
     * Returns a db instance
     *
     * @return db instance
     */
    public function getInstance() {
        $className = $this->extension . 'Db';
        if(class_exists($className)) {
            $dbFactory = new $className($this->dbInfo);
            if($dbFactory instanceof DbResource) {
                return $dbFactory;
            } else {
                die($className .'does not implement DbRerousce.');
            }
        } else {
            die(DBTYPE.' extension not found. See config.php and make sure the necessary extensions are installed.');
        }

    }
}


