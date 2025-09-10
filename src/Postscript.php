<?php

namespace Porter;

/**
 * Custom data finalization post-migration for database targets.
 *
 * Extend this class per-Target as required. It will automatically run after the Target of the same name.
 * If using file-based or remote storage, handle this outside Porter instead.
 */
abstract class Postscript
{
    /**
     * @var Storage Where the data is being sent.
     */
    protected Storage $outputStorage;
    protected Storage $postscriptStorage;

    /** Main process, custom per package. */
    abstract public function run(Migration $port): void;

    /**
     * Only Target database connection required; don't care what Source was.
     *
     * @param Storage $outputStorage
     * @param Storage $postscriptStorage
     */
    public function __construct(Storage $outputStorage, Storage $postscriptStorage)
    {
        $this->outputStorage = $outputStorage;
        $this->postscriptStorage = $postscriptStorage;
    }
}
