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
    /** @var ConnectionManager */
    protected ConnectionManager $connection;

    /**
     * @var Storage Where the data is being sent.
     */
    protected Storage $storage;

    /** Main process, custom per package. */
    abstract public function run(ExportModel $ex);

    /**
     * Only Target database connection required; don't care what Source was.
     *
     * @param ConnectionManager $connection
     */
    public function __construct(Storage $storage, ConnectionManager $connection)
    {
        $this->storage = $storage;
        $this->connection = $connection;
    }
}
