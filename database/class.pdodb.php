<?php

/**
 * Class MysqlDB
 */
class PdoDB implements DbResource {
    /**
     * @var mysql resource
     */
    private $link = null;

    /**
     * @var query result
     */
    private $result = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $args) {
        if (!defined('PDO::ATTR_DRIVER_NAME')) {
            die('PDO extension not found. Make sure the necessary extensions are installed.');
        }
        try {
            $this->link = new PDO('mysql:host='.$args['dbhost'].';dbname='.$args['dbname'], $args['dbuser'], $args['dbpass']);
            $this->link->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        } catch (Throwable $t) {
            // Executed only in PHP 7, will not match in PHP 5
            echo $t . PHP_EOL;
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
            $this->result->closeCursor();
        }
        $this->result = $this->link->query($sql);

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
    public function nextRow() {
        $row = $this->result->fetch(PDO::FETCH_ASSOC);

        if (isset($row)) {
            return $row;
        }

        $this->result->closeCursor();
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function escape($sql) {
        return $this->link->quote($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function close() {
        $this->result->closeCursor();
        $this->link = null;
    }
}

// Closing PHP tag required. (make.php)
?>
