<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace Porter;

/**
 * Generic controller implemented by forum-specific ones.
 */
class Controller
{
    /**
     * Export workflow.
     */
    public static function doExport(Source $source, ExportModel $model)
    {
        $model->verifySource($source->sourceTables);

        if ($source::getCharSetTable()) {
            $model->setCharacterSet($source::getCharSetTable());
        }

        $start = microtime(true);
        $model->comment('Export Started: ' . date('Y-m-d H:i:s'));

        $model->begin();
        $source->run($model);
        $model->end();

        $model->comment('Export Completed: ' . date('Y-m-d H:i:s'));
        $model->comment(sprintf('Elapsed Time: %s', formatElapsed(microtime(true) - $start)));

        if ($model->testMode || $model->captureOnly) {
            $queries = implode("\n\n", $model->queryRecord);
            $model->comment($queries, true);
        }
    }

    public static function doImport()
    {
        //
    }

    /**
     * Called by router to setup and run main export process.
     */
    public static function run(Request $request)
    {
        // Remove time limit.
        set_time_limit(0);

        // Set up export storage.
        if ($request->get('output') === 'file') { // @todo Perhaps abstract to a storageFactory
            $storage = new Storage\File();
        } else {
            $targetConnection = new Connection($request->get('target') ?? '');
            $storage = new Storage\Database($targetConnection);
        }

        // Export.
        $source = sourceFactory($request->get('package'));
        $sourceConnection = new Connection($request->get('source'));
        $exportModel = exportModelFactory($request, $sourceConnection, $storage); // @todo Pass options not Request
        self::doExport($source, $exportModel);

        // Import.
        if (false && $request->get('output') !== 'file') {
            $target = targetFactory($request->get('output'));
            if ($request->get('target')) {
                $target->connection = $targetConnection;
            }
            self::doImport();
        }

        // Write the results (web only).
        if (!defined('CONSOLE')) {
            Render::viewResult($exportModel->comments);
        }
    }
}
