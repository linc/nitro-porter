<?php

class MysqlDatabase implements DatabaseAbstraction {
    /**
     * Execute an sql statement and return the result.
     *
     * @param string $sql
     * @param bool|string $indexColumn
     * @return array
     */
    public function get($sql, $indexColumn = false) {
        $r = $this->_query($sql, true);
        $result = array();

        while ($row = mysql_fetch_assoc($r)) {
            if ($indexColumn) {
                $result[$row[$indexColumn]] = $row;
            } else {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * Determine the character set of the origin database.
     *
     * @param string $table
     * @return string|bool Character set name or false.
     */
    public function getCharacterSet($table) {
        // First get the collation for the database.
        $data = $this->query("show table status like ':_{$table}';");
        if (!$data) {
            return false;
        }
        if ($statusRow = mysql_fetch_assoc($data)) {
            $collation = $statusRow['Collation'];
        } else {
            return false;
        }

        // Grab the character set from the database.
        $data = $this->query("show collation like '$collation'");
        if (!$data) {
            return false;
        }
        if ($collationRow = mysql_fetch_assoc($data)) {
            $characterSet = $collationRow['Charset'];
            if (!defined('PORTER_CHARACTER_SET')) {
                define('PORTER_CHARACTER_SET', $characterSet);
            }

            return $characterSet;
        }

        return false;
    }

    /**
     * Search for table prefix through the origin database.
     *
     * @return array
     */
    public function getDatabasePrefixes() {
        // Grab all of the tables.
        $data = $this->query('show tables');
        if ($data === false) {
            return array();
        }

        // Get the names in an array for easier parsing.
        $tables = array();
        while (($row = mysql_fetch_array($data, MYSQL_NUM)) !== false) {
            $tables[] = $row[0];
        }
        sort($tables);

        $prefixes = array();

        // Loop through each table and get its prefixes.
        foreach ($tables as $table) {
            $pxFound = false;
            foreach ($prefixes as $pxIndex => $px) {
                $newPx = $this->_getPrefix($table, $px);
                if (strlen($newPx) > 0) {
                    $pxFound = true;
                    if ($newPx != $px) {
                        $prefixes[$pxIndex] = $newPx;
                    }
                    break;
                }
            }
            if (!$pxFound) {
                $prefixes[] = $table;
            }
        }

        return $prefixes;
    }

    /**
     *Determine tables and columns of the query.
     *
     * @param $query
     * @param bool $key
     * @return array
     */
    public function getQueryStructure($query, $key = false) {
        $queryStruct = rtrim($query, ';') . ' limit 1';
        if (!$key) {
            $key = md5($queryStruct);
        }
        if (isset($this->_queryStructures[$key])) {
            return $this->_queryStructures[$key];
        }

        $r = $this->query($queryStruct, true);
        $i = 0;
        $result = array();
        while ($i < mysql_num_fields($r)) {
            $meta = mysql_fetch_field($r, $i);
            $result[$meta->name] = $meta->table;
            $i++;
        }
        $this->_queryStructures[$key] = $result;
        return $result;
    }

    /**
     * Do standard HTML decoding in SQL to speed things up.
     *
     * @param string $tableName
     * @param string $columnName
     * @param string $PK
     */
    public function HTMLDecoderDb($tableName, $columnName, $PK) {
        $common = array('&amp;' => '&', '&lt;' => '<', '&gt;' => '>', '&apos;' => "'", '&quot;' => '"', '&#39;' => "'");
        foreach ($common as $from => $to) {
            $fromQ = mysql_escape_string($from);
            $toQ = mysql_escape_string($to);
            $sql = "update :_{$tableName} set $columnName = replace($columnName, '$fromQ', '$toQ') where $columnName like '%$fromQ%'";

            $this->query($sql);
        }

        // Now decode the remaining rows.
        $sql = "select * from :_$tableName where $columnName like '%&%;%'";
        $result = $this->query($sql, true);
        while ($row = mysql_fetch_assoc($result)) {
            $from = $row[$columnName];
            $to = HTMLDecoder($from);

            if ($from != $to) {
                $toQ = mysql_escape_string($to);
                $sql = "update :_{$tableName} set $columnName = '$toQ' where $PK = {$row[$PK]}";
                $this->query($sql, true);
            }
        }
    }

    /**
     * Determine if an index exists in a table
     *
     * @param $indexName Name of the index to verify
     * @param $table Name of the table the target index exists in
     * @return bool True if index exists, false otherwise
     */
    public function indexExists($indexName, $table) {
        $indexName = mysql_real_escape_string($indexName);
        $table = mysql_real_escape_string($table);

        $result = $this->query("show index from `{$table}` WHERE Key_name = '{$indexName}'", true);

        return $result && mysql_num_rows($result);
    }

    /**
     * Execute a SQL query on the current connection.
     *
     * @see Query()
     * @param $sql
     * @param bool $buffer
     * @return resource
     */
    public function _query($sql, $buffer = false) {
        if (isset($this->_lastResult) && is_resource($this->_lastResult)) {
            mysql_free_result($this->_lastResult);
        }
        $sql = str_replace(':_', $this->prefix, $sql); // replace prefix.
        if ($this->sourcePrefix) {
            $sql = preg_replace("`\b{$this->sourcePrefix}`", $this->prefix, $sql); // replace prefix.
        }

        $sql = rtrim($sql, ';') . ';';

        $connection = @mysql_connect($this->_host, $this->_username, $this->_password);
        mysql_select_db($this->_dbName);
        mysql_query("set names {$this->characterSet}");

        if ($buffer) {
            $result = mysql_query($sql, $connection);
        } else {
            $result = mysql_unbuffered_query($sql, $connection);
            if (is_resource($result)) {
                $this->_lastResult = $result;
            }
        }

        if ($result === false) {
            echo '<pre>',
            htmlspecialchars($sql),
            htmlspecialchars(mysql_error($connection)),
            '</pre>';
            trigger_error(mysql_error($connection));
        }

        return $result;
    }

    /**
     * Checks whether or not a table and columns exist in the database.
     *
     * @param string $table The name of the table to check.
     * @param array $columns An array of column names to check.
     * @return bool|array The method will return one of the following
     *  - true: If table and all of the columns exist.
     *  - false: If the table does not exist.
     *  - array: The names of the missing columns if one or more columns don't exist.
     */
    public function exists($table, $columns = array()) {
        static $_exists = array();

        if (!isset($_exists[$table])) {
            $result = $this->query("show table status like ':_$table'", true);
            if (!$result) {
                $_exists[$table] = false;
            } elseif (!mysql_fetch_assoc($result)) {
                $_exists[$table] = false;
            } else {
                mysql_free_result($result);
                $desc = $this->query('describe :_' . $table);
                if ($desc === false) {
                    $_exists[$table] = false;
                } else {
                    if (is_string($desc)) {
                        die($desc);
                    }

                    $cols = array();
                    while (($TD = mysql_fetch_assoc($desc)) !== false) {
                        $cols[$TD['Field']] = $TD;
                    }
                    mysql_free_result($desc);
                    $_exists[$table] = $cols;
                }
            }
        }

        if ($_exists[$table] == false) {
            return false;
        }

        $columns = (array)$columns;

        if (count($columns) == 0) {
            return true;
        }

        $missing = array();
        $cols = array_keys($_exists[$table]);
        foreach ($columns as $column) {
            if (!in_array($column, $cols)) {
                $missing[] = $column;
            }
        }

        return count($missing) == 0 ? true : $missing;
    }

    /**
     * Checks all required source tables are present.
     *
     * @param array $requiredTables
     * @return array|string
     */
    public function verifySource($requiredTables) {
        $missingTables = false;
        $countMissingTables = 0;
        $missingColumns = array();

        foreach ($requiredTables as $reqTable => $reqColumns) {
            $tableDescriptions = $this->query('describe :_' . $reqTable);
            //echo 'describe '.$prefix.$reqTable;
            if ($tableDescriptions === false) { // Table doesn't exist
                $countMissingTables++;
                if ($missingTables !== false) {
                    $missingTables .= ', ' . $reqTable;
                } else {
                    $missingTables = $reqTable;
                }
            } else {
                // Build array of columns in this table
                $presentColumns = array();
                while (($TD = mysql_fetch_assoc($tableDescriptions)) !== false) {
                    $presentColumns[] = $TD['Field'];
                }
                // Compare with required columns
                foreach ($reqColumns as $reqCol) {
                    if (!in_array($reqCol, $presentColumns)) {
                        $missingColumns[$reqTable][] = $reqCol;
                    }
                }

                mysql_free_result($tableDescriptions);
            }
        }

        // Return results
        if ($missingTables === false) {
            if (count($missingColumns) > 0) {
                $result = array();

                // Build a string of missing columns.
                foreach ($missingColumns as $table => $columns) {
                    $result[] = "The $table table is missing the following column(s): " . implode(', ', $columns);
                }

                return implode("<br />\n", $result);
            } else {
                return true;
            } // Nothing missing!
        } elseif ($countMissingTables == count($requiredTables)) {
            $result = 'The required tables are not present in the database. Make sure you entered the correct database name and prefix and try again.';

            // Guess the prefixes to notify the user.
            $prefixes = $this->getDatabasePrefixes();
            if (count($prefixes) == 1) {
                $result .= ' Based on the database you provided, your database prefix is probably ' . implode(', ',
                        $prefixes);
            } elseif (count($prefixes) > 0) {
                $result .= ' Based on the database you provided, your database prefix is probably one of the following: ' . implode(', ',
                        $prefixes);
            }

            return $result;
        } else {
            return 'Missing required database tables: ' . $missingTables;
        }
    }
}
