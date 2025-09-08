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
     * @param ExportModel $model
     */
    public static function doExport(Source $source, ExportModel $model): void
    {
        $model->verifySource($source->sourceTables);

        if ($source::getCharsetTable()) {
            $model->setCharacterSet($source::getCharsetTable());
        }

        $model->begin();
        $source->run($model);
        $model->end();

        if (\Porter\Config::getInstance()->debugEnabled() || $model->captureOnly) {
            $queries = implode("\n\n", $model->queryRecord);
            //$model->comment($queries, true);
        }
    }

    /**
     * Import workflow.
     *
     * @param Target $target
     * @param ExportModel $model
     */
    public static function doImport(Target $target, ExportModel $model): void
    {
        $model->begin();
        $target->validate($model);
        $target->run($model);
        $model->end();
    }

    /**
     * Finalize the import (if the optional postscript class exists).
     *
     * Use a separate database connection since re-querying data may be necessary.
     *    -> "Cannot execute queries while other unbuffered queries are active."
     *
     * @param string $postscript
     * @param string $output
     * @param ExportModel $exportModel
     */
    protected static function doPostscript(string $postscript, string $output, ExportModel $exportModel): void
    {
        $postConnection = new ConnectionManager($output);
        $postscript = postscriptFactory($postscript, $exportModel->getOutputStorage(), $postConnection);
        if ($postscript) {
            $exportModel->comment("Postscript found and running...");
            $postscript->run($exportModel);
        } else {
            $exportModel->comment("No Postscript found.");
        }
    }

    /**
     * Do some intelligent configuration of the migration process.
     *
     * @param Source $source
     * @param Target $target
     */
    public static function setFlags(Source $source, Target $target): void
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
     */
    public static function run(Request $request): void
    {
        // Break down the Request.
        $source = sourceFactory($request->getSource());
        $target = targetFactory($request->getTarget());
        $inputName = $request->getInput();
        $outputName = $request->getOutput();
        $sourcePrefix = $request->getInputTablePrefix();
        $targetPrefix = (empty($target)) ? '' : $request->getOutputTablePrefix(); // @todo Make 'file' explicit output.
        $dataTypes = $request->getDatatypes();

        // Setup model.
        $inputCM = new ConnectionManager($inputName); // @todo Delete after Sources are all moved to $inputStorage.
        $inputDB = new \Porter\Database\DbFactory($inputCM->getAllInfo(), 'pdo');
        $inputStorage = new Storage\Database(new ConnectionManager($inputName));
        if (empty($target)) { // @todo storageFactory
            $porterStorage = new Storage\File(); // Only 1 valid 'file' type currently.
            $outputStorage = new Storage\File(); // @todo dead variable (halts at porter step)
        } else {
            $porterStorage = new Storage\Database(new ConnectionManager($outputName));
            $outputStorage = new Storage\Database(new ConnectionManager($outputName));
        }
        $model = exportModelFactory(
            $inputDB, // @deprecated
            $inputStorage,
            $porterStorage,
            $outputStorage,
            $sourcePrefix,
            $targetPrefix,
            $dataTypes,
            ($outputName === 'sql')
        );

        // Setup file transfer.
        $fileTransfer = new FileTransfer($source, $target, $inputStorage);

        // Log request.
        $model->comment("NITRO PORTER RUNNING...");
        $model->comment("Porting " . $source->getName() . " to " . ($target ? $target->getName() : 'file'));
        $model->comment("Input: " . $inputCM->getAlias() . ' (' . ($sourcePrefix ?? 'no prefix') . ')');
        $model->comment("Porter: " . $porterStorage->getAlias() . ' (PORT_)');
        $model->comment("Output: " . $outputStorage->getAlias() . ' (' . ($targetPrefix ?? 'no prefix') . ')');

        // Setup & log flags.
        if ($target) {
            self::setFlags($source, $target);
            $model->comment("? 'Use Discussion Body' = " .
                ($target->getDiscussionBodyMode() ? 'Enabled' : 'Disabled'));
        }

        // Remove limits & start timer.
        set_time_limit(0);
        ini_set('memory_limit', '256M');
        $start = microtime(true);
        $model->comment("\n" . sprintf(
            '[ STARTED at %s ]',
            date('H:i:s e')
        ) . "\n");

        // Export (Source -> `PORT_`).
        self::doExport($source, $model);

        // Import (`PORT_` -> Target).
        if ($target) {
            self::doImport($target, $model);
            // Postscript names must match target names currently.
            self::doPostscript($target->getName(), $outputName, $model);
        }

        // File transfer.
        $fileTransfer->run();

        // Report.
        $model->comment("\n" . sprintf(
            '[ FINISHED at %s after running for %s ]',
            date('H:i:s e'),
            formatElapsed(microtime(true) - $start)
        ));
        $model->comment("[ After testing, you may delete any `PORT_` database tables. ]");
        $model->comment('[ Porter never migrates user permissions! Reset user permissions afterward. ]' . "\n\n");
    }
}
