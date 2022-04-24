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
    /** @var Connection */
    protected Connection $connection;

    /** Main process, custom per package. */
    abstract public function run(ExportModel $ex);

    /**
     * Only Target database connection required; don't care what Source was.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
}
