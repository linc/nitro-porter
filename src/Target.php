<?php

namespace Porter;

abstract class Target
{
    public const SUPPORTED = [
        'name' => '',
        'prefix' => '',
        'charset_table' => '',
        'avatarsPrefix' => '',
        'avatarThumbnailsPrefix' => '',
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

    public ConnectionManager $connection;

    /**
     * Get name of the source package.
     *
     * @return array
     * @see Target::setSources()
     */
    public static function getSupport(): array
    {
        return static::SUPPORTED;
    }

    /**
     * Get name of the target package.
     *
     * @return string
     */
    public static function getName(): string
    {
        return static::SUPPORTED['name'];
    }

    /**
     * Get default table prefix of the target package.
     *
     * @return string
     */
    public static function getPrefix(): string
    {
        return static::SUPPORTED['prefix'];
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
    abstract public function run(Migration $port): void;

    /** Enforce data constraints required by the target platform. */
    abstract public function validate(Migration $port): void;

    /**
     * Get current max value of a column on a table in output (target).
     *
     * Do not use porter (PORT_) tables because we may have added records elsewhere.
     *
     * @param string $name
     * @param string $table
     * @param Migration $ex
     * @return int
     */
    protected function getMaxValue(string $name, string $table, Migration $ex): int
    {
        $max = $ex->dbOutput()->table($table)
            ->selectRaw('max(`' . $name . '`) as id')
            ->limit(1)->get()->pluck('id');
        return $max[0] ?? 0;
    }
}
