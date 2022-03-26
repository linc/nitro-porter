<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
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

        if ($model->testMode || $model->captureOnly) {
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
        if ($source->getDiscussionBodyMode() === false && $target->getDiscussionBodyMode() === false) {
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

        // Set up export storage.
        if ($request->get('output') === 'file') { // @todo Perhaps abstract to a storageFactory
            $targetConnection = new Connection(); // Unused but required by ExportModel regardless.
            $storage = new Storage\File();
        } else {
            $targetConnection = new Connection($request->get('target') ?? '');
            $storage = new Storage\Database($targetConnection);
        }

        // Setup source & model.
        $source = sourceFactory($request->get('package'));
        $sourceConnection = new Connection($request->get('source'));
        // @todo Pass options not Request
        $exportModel = exportModelFactory($request, $sourceConnection, $storage, $targetConnection);

        // Setup target & modes.
        $target = false;
        if ($request->get('output') !== 'file') {
            $target = targetFactory($request->get('output'));
            self::setModes($source, $target);
        }

        // Start timer.
        $start = microtime(true);
        $exportModel->comment('START: ' . date('Y-m-d H:i:s'));

        // Export.
        self::doExport($source, $exportModel);

        // Import.
        if ($target) {
            if ($request->get('target')) {
                $target->connection = $targetConnection; // @todo Allow separate connection for this.
            }
            $exportModel->tarPrefix = $target::SUPPORTED['prefix']; // @todo Wrap these refs.
            self::doImport($target, $exportModel);
        }

        // End timer.
        $exportModel->comment('END: ' . date('Y-m-d H:i:s'));
        $exportModel->comment(sprintf('Elapsed: %s', formatElapsed(microtime(true) - $start)));

        // Write the results (web only).
        if (!defined('CONSOLE')) {
            Render::viewResult($exportModel->comments);
        }
    }
}
