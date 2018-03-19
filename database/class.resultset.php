<?php

/**
 * Class ResultSet
 */
class ResultSet {
    /**
     * @var DbResource
     */
    private $dbResource;

    /**
     * ResultSet constructor.
     *
     * @param DbResource $dbResource
     */
    public function __construct(DbResource $dbResource) {
        $this->dbResource = $dbResource;
    }

    /**
     * Iterate to new result row via the db resource
     *
     * @return array|bool
     */
    public function nextResultRow() {
        return $this->dbResource->nextRow();
    }
}

// Closing PHP tag required. (make.php)
?>
