<?php

namespace Porter;

abstract class Target
{
    public const SUPPORTED = [
        'name' => '',
        'prefix' => '',
        'charset_table' => '',
        'features' => [],
    ];

    protected const FLAGS = [];

    protected bool $useDiscussionBody = true;

    public Connection $connection;

    /**
     * Register supported features.
     */
    public static function getSupport(): array
    {
        return static::SUPPORTED;
    }

    /**
     * Retrieve characteristics of the package.
     *
     * @param string $name
     * @return mixed|null
     */
    public static function getFlag(string $name)
    {
        return (isset(static::FLAGS[$name])) ? static::FLAGS[$name] : null;
    }

    public function getDiscussionBodyMode(): bool
    {
        return $this->useDiscussionBody;
    }

    public function skipDiscussionBody()
    {
        $this->useDiscussionBody = false;
    }

    abstract public function run(ExportModel $ex);
}
