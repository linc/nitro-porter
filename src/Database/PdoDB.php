<?php

namespace Porter\Database;

use PDO;

/**
 * Class MysqlDB
 *
 * @deprecated
 */
class PdoDB implements DbResource
{
    /**
     * @var ?PDO
     */
    private ?PDO $link = null;

    /**
     * @var \PDOStatement|false|null query result
     */
    private $result = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(PDO $pdo)
    {
        // Mind if I cut in? Bridge to removing this entirely.
        $this->link = $pdo;
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
}
