<?php

class ResultSet {
    private $dbResource;

    public function __construct(DbResource $dbResource) {
        $this->dbResource = $dbResource;
    }

    public function nextResultRow() {
        return $this->dbResource->nextRow();
    }
}

// Closing PHP tag required. (make.php)
?>
