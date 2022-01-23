<?php

namespace Porter\Database;

/**
 * Class ResultSet
 */
class ResultSet
{
    /**
     * @var DbResource
     */
    private $dbResource;

    /**
     * ResultSet constructor.
     *
     * @param DbResource $dbResource
     */
    public function __construct(DbResource $dbResource)
    {
        $this->dbResource = $dbResource;
    }

    /**
     * Iterate to new result row via the db resource.
     *
     * @param bool $assoc will return result row as an enumerated array if false.
     * @return array|bool
     */
    public function nextResultRow($assoc = true)
    {
        return $this->dbResource->nextRow($assoc);
    }
}
