<?php

namespace Porter;

abstract class Source
{
    public const SUPPORTED = [
        'name' => '',
        'prefix' => '',
        'charset_table' => '',
        'hashmethod' => '',
        'options' => [],
        'features' => [],
    ];

    /** @var array Settings that change Source behavior. */
    protected const FLAGS = [];

    /**
     * If this is 'false', skip extract first post content from `Discussions.Body`.
     *
     * Do not change this default in child Sources.
     * Use `'hasDiscussionBody' => false` in FLAGS to declare your Source can skip this step.
     *
     * @var bool
     * @see Source::getDiscussionBodyMode()
     * @see Source::skipDiscussionBody()
     */
    protected bool $useDiscussionBody = true;

    /**
     * @deprecated
     * @var ?ExportModel
     */
    public ?ExportModel $exportModel = null;

    /**
     * @deprecated
     * @var array Required tables, columns set per exporter
     */
    public array $sourceTables = [];

    /**
     * Forum-specific export routine
     */
    abstract public function run(ExportModel $ex); // @phpstan-ignore missingType.return

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
     * @return mixed
     */
    public static function getFlag(string $name): mixed
    {
        return (isset(static::FLAGS[$name])) ? static::FLAGS[$name] : null;
    }

    /**
     * Whether to connect the OP to the discussion record.
     *
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

    /**
     * @return string
     */
    public static function getCharSetTable(): string
    {
        $charset = '';
        if (isset(self::getSupport()['charset_table'])) {
            $charset = self::getSupport()['charset_table'];
        }
        return $charset;
    }
}
