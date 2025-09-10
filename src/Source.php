<?php

namespace Porter;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

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
     * @var ?Migration
     */
    public ?Migration $port = null;

    /**
     * @deprecated
     * @var array Required tables, columns set per exporter
     */
    public array $sourceTables = [];

    /**
     * Forum-specific export routine
     */
    abstract public function run(Migration $port); // @phpstan-ignore missingType.return

    /**
     * Get name of the source package.
     *
     * @return array
     * @see Support::setSources()
     */
    public static function getSupport(): array
    {
        return static::SUPPORTED;
    }

    /**
     * Get name of the source package.
     *
     * @return string
     */
    public static function getName(): string
    {
        return static::SUPPORTED['name'];
    }

    /**
     * Get default table prefix of the source package.
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
     */
    public function skipDiscussionBody(): void
    {
        $this->useDiscussionBody = false;
    }

    /**
     * @return string
     */
    public static function getCharsetTable(): string
    {
        $charset = '';
        if (isset(self::SUPPORTED['charset_table'])) { // @phpstan-ignore isset.offset
            $charset = self::SUPPORTED['charset_table'];
        }
        return $charset;
    }

    /**
     * Query builder that selects values `sourcename` & `targetname`.
     *
     * @param Connection $c
     * @return ?Builder
     */
    public function attachmentsData(Connection $c): ?Builder
    {
        return null;
    }
}
