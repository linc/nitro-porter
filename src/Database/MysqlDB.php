<?php
namespace NitroPorter;

/**
 * Class MysqlDB
 */
class MysqlDB implements DbResource {
    /** @var mysql resource */
    private $link = null;

    /** @var query result */
    private $result = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $args) {
        if (!function_exists('mysql_connect')) {
            die('MySQL extension not found. MySQL extension was removed from php 7.0+. See http://php.net/manual/en/mysql.requirements.php for more information.');
        }
        try {
            $this->link = mysql_connect($args['dbhost'], $args['dbuser'], $args['dbpass'], true);
            if (!$this->link) {
                die('Could not connect: ' . mysql_error());
            }
            mysql_select_db($args['dbname']);
        } catch (Throwable $t) {
            // Executed only in PHP 7, will not match in PHP 5
            echo $t . PHP_EOL;
            die();
        } catch (Exception $e) {
            // Executed only in PHP 5, will not be reached in PHP 7
            echo $e . PHP_EOL;
            die();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query($sql) {
        if (isset($this->result)) {
            mysql_free_result($this->result);
        }
        $this->result = mysql_unbuffered_query($sql, $this->link);

        if ($this->result === false) {
            $this->error($sql);
            return false;
        }
        return new ResultSet($this);
    }

    /**
     * {@inheritdoc}
     */
    public function error($sql) {
        echo '<pre>',
        htmlspecialchars($sql),
        htmlspecialchars(mysql_error($this->link)),
        '</pre>';
        trigger_error(mysql_error($this->link));
    }

    /**
     * {@inheritdoc}
     */
    public function nextRow($assoc) {
        if ($assoc) {
            $row = mysql_fetch_assoc($this->result);
        } else {
            $row = mysql_fetch_row($this->result);
        }

        if (isset($row)) {
            return $row;
        } else  {
            mysql_free_result($this->result);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function escape($sql) {
        return mysql_real_escape_string($sql, $this->link);
    }

    /**
     * {@inheritdoc}
     */
    public function close() {
        mysql_free_result($this->result);
        mysql_close($this->link);
    }
}


