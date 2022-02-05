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

    /**
     * Called by router to setup and run main export process.
     */
    public static function run(Request $request)
    {
        // Remove time limit.
        set_time_limit(0);

        // Wire new database.
        $connection = new Connection($request->get('source'));
        $info = $connection->getAllInfo();
        bootDatabase($info);

        // Export.
        $source = sourceFactory($request->get('package'));
        $target = targetFactory($request->get('output'));
        $exportModel = exportModelFactory($request, $info, $target); // @todo Pass options not Request
        self::doExport($source, $exportModel);

        // Write the results (web only).
        if (!defined('CONSOLE')) {
            Render::viewResult($exportModel->comments);
        }
    }
}
