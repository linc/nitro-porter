<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace Porter;

class Request
{
    private static $instance = null;

    protected $request = [];

    public static function instance(): self
    {
        if (self::$instance == null) {
            self::$instance = new Request();
        }

        return self::$instance;
    }

    /**
     * Get a single value from the request.
     *
     * @param string $name
     * @return ?string
     */
    public function get(string $name): ?string
    {
        $value = null;

        if (isset($this->request[$name])) {
            $value = $this->request[$name];
        }

        return $value;
    }

    /**
     * Get all data from request.
     *
     * @return array
     */
    public function getAll(): array
    {
        return $this->request;
    }

    /**
     * Set request data on this object.
     *
     * @param array $request
     */
    public function load(array $request): void
    {
        $this->request = $request;
    }
}
