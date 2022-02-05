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
        $model->beginFileExport();
        $model->comment('Export Started: ' . date('Y-m-d H:i:s'));

        $source->run($model);

        $model->comment($model->path);
        $model->comment('Export Completed: ' . date('Y-m-d H:i:s'));
        $model->comment(sprintf('Elapsed Time: %s', formatElapsed(microtime(true) - $start)));

        if ($model->testMode || Request::instance()->get('dumpsql') || $model->captureOnly) {
            $queries = implode("\n\n", $model->queryRecord);
            $model->comment($queries, true);
        }

        $model->endFileExport();
    }

    /**
     * Import workflow.
     */
    public static function doImport(Target $target, ImportModel $model)
    {
        $target->run($model);
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
        $exportModel = exportModelFactory($request, $info); // @todo Pass options not Request
        $source = sourceFactory($request->get('package'));
        self::doExport($source, $exportModel);

        // Import.
        if ($request->get('output') !== '') {
            $importModel = importModelFactory();
            $target = targetFactory($request->get('output'));
            self::doImport($target, $importModel);
        }

        // Write the results (web only).
        if (!defined('CONSOLE')) {
            Render::viewResult($exportModel->comments);
        }
    }
}
