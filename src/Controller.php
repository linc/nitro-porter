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

        $model->beginExport();
        $source->run($model);
        $model->endExport();
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
        $model = modelFactory($request, $info); // @todo Pass options not Request
        $source = sourceFactory($request->get('package'));
        self::doExport($source, $model);

        // Write the results (web only).
        if (!defined('CONSOLE')) {
            Render::viewResult($model->comments);
        }
    }
}
