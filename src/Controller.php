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
    public static function doExport(Source $source, ExportModel $model)
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
            $model->comment($queries, true);
        }
    }

    /**
     * Import workflow.
     *
     * @param Target $target
     * @param ExportModel $model
     */
    public static function doImport(Target $target, ExportModel $model)
    {
        $model->begin();
        $target->validate($model);
        $target->run($model);
        $model->end();
    }

    /**
     * Do some intelligent configuration of the migration process.
     *
     * @param Source $source
     * @param Target $target
     */
    public static function setModes(Source $source, Target $target)
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
     * Called by router to set up and run main export process.
     */
    public static function run(Request $request)
    {
        // Remove time limit.
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        // Set up export storage.
        if ($request->getTarget() === 'file') { // @todo Perhaps abstract to a storageFactory
            $storage = new Storage\File();
        } else {
            $targetCM = new ConnectionManager($request->getOutput());
            $storage = new Storage\Database($targetCM);
        }

        // Setup source & model.
        $source = sourceFactory($request->getSource());
        $outputCM = new ConnectionManager($request->getOutput());
        $inputCM = new ConnectionManager($request->getInput());
        $exportModel = exportModelFactory($request, $source, $inputCM, $storage, $outputCM);

        // No permissions warning.
        $exportModel->comment('[ Porter never migrates user permissions! Reset user permissions afterward. ]' . "\n");

        // Log source.
        $exportModel->comment("Source: " . $source::SUPPORTED['name'] . " (" . $inputCM->getAlias() . ")");

        // Setup target & modes.
        $target = false;
        if ($request->getTarget() !== 'file') {
            $target = targetFactory($request->getTarget());
            // Log target.
            $exportModel->comment("Target: " . $target::SUPPORTED['name'] . " (" . $outputCM->getAlias() . ")");

            self::setModes($source, $target);
            // Log flags.
            $exportModel->comment("Flag: Use Discussion Body: " .
                ($target->getDiscussionBodyMode() ? 'Enabled' : 'Disabled'));
        }

        // Start timer.
        $start = microtime(true);

        // Export.
        self::doExport($source, $exportModel);

        // Import.
        if ($target) {
            $exportModel->tarPrefix = $target::SUPPORTED['prefix']; // @todo Wrap these refs.
            self::doImport($target, $exportModel);

            // Finalize the import (if the optional postscript class exists).
            // Use a separate database connection since re-querying data may be necessary.
            // -> "Cannot execute queries while other unbuffered queries are active."
            $postConnection = new ConnectionManager($request->getOutput());
            $postscript = postscriptFactory($request->getTarget(), $storage, $postConnection);
            if ($postscript) {
                $exportModel->comment("Postscript found and running...");
                $postscript->run($exportModel);
            } else {
                $exportModel->comment("No Postscript found.");
            }

            // Doing this cleanup automatically is complex, so tell them to do it manually for now.
            $exportModel->comment("After testing import, you may delete `PORT_` database tables.");
        }

        // End timer & report.
        $exportModel->comment(
            sprintf('ELAPSED — %s', formatElapsed(microtime(true) - $start)) .
            ' (' . date('H:i:s', (int)$start) . ' - ' . date('H:i:s') . ')'
        );
    }
}
