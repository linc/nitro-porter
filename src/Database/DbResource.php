<?php

namespace Porter\Database;

use PDO;

/**
 * @deprecated
 */
interface DbResource
{
    /**
     * MysqlDB constructor.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo);

    /**
     * Query method.
     *
     * @param string $sql
     * @return bool|ResultSet return instance of ResultSet on success false on failure
     */
    public function query($sql);

    /**
     * Prints the mysql error.
     *
     * @param string $sql query
     */
    public function error($sql);

    /**
     * Fetch the new result row.
     *
     * @param  bool $assoc
     * @return array|bool returns the next row if possible false if we've reached the end of the result set.
     */
    public function nextRow($assoc);
}
