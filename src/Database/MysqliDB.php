<?php

namespace NitroPorter;

/**
 * Class MysqliDB
 */
class MysqliDB implements DbResource
{

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
    public function __construct(array $args)
    {
        if (!function_exists('mysqli_connect')) {
            die('MySQLi extension not found. See config.php and make sure the necessary extensions are installed.');
        }
        try {
            $this->link = mysqli_connect($args['dbhost'], $args['dbuser'], $args['dbpass'], $args['dbname']);
            if (!$this->link) {
                die('Could not connect: ' . mysqli_error());
            }
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
    public function query($sql)
    {
        if (isset($this->result) && $this->result instanceof mysqli_result) {
            mysqli_free_result($this->result);
        }
        $result = $this->link->query($sql, MYSQLI_USE_RESULT);

        if ($result === false) {
            $this->error($sql);
            return false;
        }
        $this->result = $result;
        return new ResultSet($this);
    }

    /**
     * {@inheritdoc}
     */
    public function error($sql)
    {
        echo '<pre>',
        htmlspecialchars($sql),
        htmlspecialchars(mysqli_error($this->link)),
        '</pre>';
        trigger_error(mysqli_error($this->link));
    }

    /**
     * {@inheritdoc}
     */
    public function nextRow($assoc)
    {
        if ($assoc) {
            $row = mysqli_fetch_assoc($this->result);
        } else {
            $row = mysqli_fetch_row($this->result);
        }

        if (isset($row)) {
            return $row;
        } else {
            mysqli_free_result($this->result);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function escape($sql)
    {
        return mysqli_real_escape_string($this->link, $sql);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        mysqli_close($this->link);
        $this->link = null;
    }
}
