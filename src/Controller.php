<?php

/**
 *
 */

namespace Porter;

/**
 * Top-level workflows.
 */
class Controller
{
    /**
     * Export workflow.
     *
     * @param Source $source
     * @param Migration $port
     */
    protected function doExport(Source $source, Migration $port): void
    {
        $port->verifySource($source->sourceTables);

        if ($source::getCharsetTable()) {
            $port->setCharacterSet($source::getCharsetTable());
        }

        $port->begin();
        $source->run($port);
        $port->end();
    }

    /**
     * Import workflow.
     *
     * @param Target $target
     * @param Migration $port
     */
    protected function doImport(Target $target, Migration $port): void
    {
        $port->begin();
        $target->validate($port);
        $target->run($port);
        $port->end();
    }

    /**
     * Finalize the import (if the optional postscript class exists).
     *
     * Use a separate database connection since re-querying data may be necessary.
     *    -> "Cannot execute queries while other unbuffered queries are active."
     *
     * @param string $postscript
     * @param Migration $port
     */
    protected function doPostscript(string $postscript, Migration $port): void
    {
        $postscript = postscriptFactory(
            $postscript,
            $port->getOutputStorage(),
            $port->getPostscriptStorage()
        );
        if ($postscript) {
            $port->comment("Postscript found and running...");
            $postscript->run($port);
        } else {
            $port->comment("No Postscript found.");
        }
    }

    /**
     * Do some intelligent configuration of the migration process.
     *
     * @param Source $source
     * @param Target $target
     */
    protected function setFlags(Source $source, Target $target): void
    {
        // If both the source and target don't store content/body on the discussion/thread record,
        // skip the conversion on both sides so we don't do joins and renumber keys for nothing.
        if (
            $source::getFlag('hasDiscussionBody') === false &&
            $target::getFlag('hasDiscussionBody') === false
        ) {
            $source->skipDiscussionBody();
            $target->skipDiscussionBody();
        }
    }

    /**
     * Setup & run the requested migration process.
     *
     * Translates `Request` into action (i.e. `Request` object should not pass beyond here).
     * @throws \Exception
     */
    public function run(Request $request): void
    {
        // Break down the Request.
        $source = sourceFactory($request->getSource());
        $target = targetFactory($request->getTarget());
        $inputName = $request->getInput();
        $outputName = $request->getOutput();
        $sourcePrefix = $request->getInputTablePrefix();
        $targetPrefix = (empty($target)) ? '' : $request->getOutputTablePrefix(); // @todo Make 'file' explicit output.
        $dataTypes = $request->getDatatypes();

        // Setup migration.
        // @todo Delete $inputDB after Sources are all moved to $inputStorage.
        $inputDB = new \Porter\Database\DbFactory((new ConnectionManager($inputName))->getAllInfo(), 'pdo');
        $inputStorage = new Storage\Database(new ConnectionManager($inputName, $sourcePrefix));
        if (empty($target)) { // @todo storageFactory
            $porterStorage = new Storage\File(); // Only 1 valid 'file' type currently.
            $outputStorage = new Storage\File(); // @todo dead variable (halts at porter step)
            $postscriptStorage = new Storage\File(); // @todo dead variable (halts at porter step)
        } else {
            $porterStorage = new Storage\Database(new ConnectionManager($outputName, 'PORT_'));
            $outputStorage = new Storage\Database(new ConnectionManager($outputName, $targetPrefix));
            $postscriptStorage = new Storage\Database(new ConnectionManager($outputName, $targetPrefix));
        }
        $port = migrationFactory(
            $inputDB, // @deprecated
            $inputStorage,
            $porterStorage,
            $outputStorage,
            $postscriptStorage,
            $sourcePrefix,
            $dataTypes,
            ($outputName === 'sql')
        );

        // Setup file transfer.
        $fileTransfer = new FileTransfer($source, $target, $inputStorage);

        // Log request.
        $port->comment("NITRO PORTER RUNNING...");
        $port->comment("Porting " . $source->getName() . " to " . ($target ? $target->getName() : 'file'));
        $port->comment("Input: " . $inputStorage->getAlias() . ' (' . ($sourcePrefix ?? 'no prefix') . ')');
        $port->comment("Porter: " . $porterStorage->getAlias() . ' (PORT_)');
        $port->comment("Output: " . $outputStorage->getAlias() . ' (' . ($targetPrefix ?? 'no prefix') . ')');

        // Setup & log flags.
        if ($target) {
            $this->setFlags($source, $target);
            $port->comment("? 'Use Discussion Body' = " .
                ($target->getDiscussionBodyMode() ? 'Enabled' : 'Disabled'));
        }

        // Remove limits & start timer.
        set_time_limit(0);
        ini_set('memory_limit', '256M');
        $start = microtime(true);
        $port->comment("\n" . sprintf(
            '[ STARTED at %s ]',
            date('H:i:s e')
        ) . "\n");

        // Export (Source -> `PORT_`).
        $this->doExport($source, $port);

        // Import (`PORT_` -> Target).
        if ($target) {
            $this->doImport($target, $port);
            // Postscript names must match target names currently.
            $this->doPostscript($target->getName(), $port);
        }

        // File transfer.
        //$fileTransfer->run();

        // Report.
        $port->comment("\n" . sprintf(
            '[ FINISHED at %s after running for %s ]',
            date('H:i:s e'),
            formatElapsed(microtime(true) - $start)
        ));
        $port->comment("[ After testing, you may delete any `PORT_` database tables. ]");
        $port->comment('[ Porter never migrates user permissions! Reset user permissions afterward. ]' . "\n\n");
    }
}
