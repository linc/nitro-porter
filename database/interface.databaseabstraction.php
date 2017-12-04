<?php

/**
 * Interface to standardize functions in the different database implementations
 */
interface DatabaseAbstraction {
    /**
     * Execute a SQL query on the current connection.
     *
     * @param $sql
     * @param bool $buffer
     */
    public function _query($sql, $buffer = false);

    /**
     * Execute an sql statement and return the result.
     *
     * @param string $sql
     * @param bool|string $indexColumn
     */
    public function get($sql, $indexColumn = false);

    /**
     * Determine the character set of the origin database.
     *
     * @param string $table
     */
    public function getCharacterSet($table);

    /**
     * Search for table prefix through the origin database.
     */
    public function getDatabasePrefixes();

    /**
     * Determine tables and columns of the query.
     *
     * @param $query
     * @param bool $key
     */
    public function getQueryStructure($query, $key = false);

    /**
     * Do standard HTML decoding in SQL to speed things up.
     *
     * @param string $tableName
     * @param string $columnName
     * @param string $PK
     */
    public function HTMLDecoderDb($tableName, $columnName, $PK);

    /**
     * Determine if an index exists in a table.
     *
     * @param $indexName Name of the index to verify
     * @param $table Name of the table the target index exists in
     */
    public function indexExists($indexName, $table);

    /**
     * Checks whether or not a table and columns exist in the database.
     *
     * @param string $table The name of the table to check.
     * @param array $columns An array of column names to check.
     */
    public function exists($table, $columns = array());

    /**
     * Checks all required source tables are present.
     *
     * @param array $requiredTables
     */
    public function verifySource($requiredTables);
}
