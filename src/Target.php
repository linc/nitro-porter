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

    /** @var array Settings that change Target behavior. */
    protected const FLAGS = [];

    /**
     * If this is 'false', skip moving first post content to `Discussions.Body`.
     *
     * Do not change this default in child Targets.
     * Use `'hasDiscussionBody' => false` in FLAGS to declare your Target can skip this step.
     *
     * @var bool
     * @see Target::getDiscussionBodyMode()
     * @see Target::skipDiscussionBody()
     */
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

    /**
     * @return bool
     */
    public function getDiscussionBodyMode(): bool
    {
        return $this->useDiscussionBody;
    }

    /**
     * Set `useDiscussionBody` to false.
     *
     * @return void
     */
    public function skipDiscussionBody()
    {
        $this->useDiscussionBody = false;
    }

    /** Do the main process for imports, table by table. */
    abstract public function run(ExportModel $ex);

    /** Enforce data constraints required by the target platform. */
    abstract public function validate(ExportModel $ex);
}
