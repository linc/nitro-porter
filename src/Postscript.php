<?php

namespace Porter;

/**
 * Custom data finalization post-migration.
 */
abstract class Postscript
{
    abstract public function run(ExportModel $ex);
}
