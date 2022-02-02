<?php

namespace Porter\Database;

/**
 * Class MysqlDB
 */
class PdoDB implements DbResource
{
    /**
     * @var \PDO|null resource
     */
    private $link = null;

    /**
     * @var \PDOStatement|false|null query result
     */
    private $result = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $args)
    {
        if (!defined('PDO::ATTR_DRIVER_NAME')) {
            die('PDO extension not found. See config.php and make sure the necessary extensions are installed.');
        }
        try {
            $this->link = new \PDO('mysql:host=' . $args['host'] . ';dbname='
                 . $args['name'] . ';charset=utf8mb4', $args['user'], $args['pass']);
            $this->link->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        } catch (\Throwable $t) {
            // Executed only in PHP 7, will not match in PHP 5
            echo $t . PHP_EOL;
            die();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
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
    public function error($sql)
    {
        echo '<pre>',
        htmlspecialchars($sql);
        print_r($this->link->errorInfo());
        echo '</pre>';
    }

    /**
     * {@inheritdoc}
     */
    public function nextRow($assoc)
    {
        $row = $this->result->fetch($assoc ? \PDO::FETCH_ASSOC : \PDO::FETCH_NUM);

        if (isset($row)) {
            return $row;
        }

        $this->result->closeCursor();
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function escape($sql)
    {
        return $this->link->quote($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->result->closeCursor();
        $this->link = null;
    }
}
