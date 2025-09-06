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

        if ($source::getCharSetTable()) {
            $model->setCharacterSet($source::getCharSetTable());
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
     * @param string $target
     * @param string $output
     * @param ExportModel $exportModel
     */
    protected static function doPostscript(string $target, string $output, ExportModel $exportModel): void
    {
        $postConnection = new ConnectionManager($output);
        $postscript = postscriptFactory($target, $exportModel->getOutputStorage(), $postConnection);
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
        // Reconcile request with config & defaults.
        $sourceName = $request->getSource();
        $targetName = $request->getTarget();
        $source = sourceFactory($sourceName);
        $target = targetFactory($targetName);
        $inputName = $request->getInput();
        $outputName = $request->getOutput();
        $sourcePrefix = $request->getInputTablePrefix();
        $targetPrefix = (empty($target)) ? '' : $request->getOutputTablePrefix();
        $dataTypes = $request->getDatatypes();

        // Setup model.
        $inputCM = new ConnectionManager($inputName); // @todo Storage for $input too.
        if ($targetName === 'file') { // @todo storageFactory
            $porterStorage = new Storage\File(); // Only 1 valid 'file' type currently.
            $outputStorage = new Storage\File(); // @todo dead variable (halts at porter step)
        } else {
            $porterStorage = new Storage\Database(new ConnectionManager($outputName)); // @todo Separate
            $outputStorage = new Storage\Database(new ConnectionManager($outputName));
        }
        $model = exportModelFactory(
            $inputCM,
            $porterStorage,
            $outputStorage,
            $sourcePrefix,
            $targetPrefix,
            $dataTypes,
            ($outputName === 'sql')
        );

        // Log request.
        $model->comment("NITRO PORTER RUNNING...");
        $model->comment("Porting " . $source::SUPPORTED['name'] . " to " . $target::SUPPORTED['name']);
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
            self::doPostscript($targetName, $outputName, $model);
        }

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
